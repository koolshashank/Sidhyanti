import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_foreground_task/flutter_foreground_task.dart';
import 'package:geolocator/geolocator.dart';

import '../api/tracking_api.dart';
import '../auth/auth_state.dart';
import '../models/duty_session.dart';
import 'location_queue_store.dart';

/// How often a fresh fix is captured even if the staff member hasn't moved.
const Duration kTrackingCaptureInterval = Duration(minutes: 5);

/// A fresh fix is also captured as soon as the staff member moves this far,
/// without waiting for the next periodic tick.
const int kTrackingDistanceFilterMeters = 50;

/// How often the local offline queue is retried against the server.
const Duration kTrackingFlushInterval = Duration(seconds: 60);

/// The actual GPS capture/upload logic all runs in the app's normal main
/// isolate (below, in [DutyTrackingService]) -- this task's only job is to
/// hold Android's foreground service (and its persistent notification)
/// open so the OS doesn't kill the app process while the screen is locked
/// or the app is minimized.
@pragma('vm:entry-point')
void dutyForegroundTaskCallback() {
  FlutterForegroundTask.setTaskHandler(_DutyForegroundTaskHandler());
}

class _DutyForegroundTaskHandler extends TaskHandler {
  @override
  Future<void> onStart(DateTime timestamp, TaskStarter starter) async {}

  @override
  void onRepeatEvent(DateTime timestamp) {}

  @override
  Future<void> onDestroy(DateTime timestamp) async {}
}

/// Owns the whole "on duty" lifecycle: starting/ending a Duty_Session on the
/// server, streaming GPS fixes while active (every 5 minutes or after a 50m
/// move), queueing them locally when offline, and flushing that queue in
/// batches once connectivity returns. Auto-stops the moment the staff member
/// is no longer authenticated (logout or an expired session).
class DutyTrackingService extends ChangeNotifier {
  final TrackingApi _trackingApi;
  final AuthState _authState;

  DutyTrackingService(this._trackingApi, this._authState) {
    _authState.addListener(_handleAuthChange);
    FlutterForegroundTask.init(
      androidNotificationOptions: AndroidNotificationOptions(
        channelId: 'duty_tracking_service',
        channelName: 'Duty Tracking',
        channelDescription: 'Shows while your location is being shared during duty hours.',
        onlyAlertOnce: true,
      ),
      iosNotificationOptions: const IOSNotificationOptions(showNotification: false, playSound: false),
      foregroundTaskOptions: ForegroundTaskOptions(
        eventAction: ForegroundTaskEventAction.nothing(),
        allowWakeLock: true,
        allowWifiLock: true,
      ),
    );
  }

  int? _sessionId;
  DateTime? _startTime;
  StreamSubscription<Position>? _positionSub;
  Timer? _periodicTimer;
  Timer? _flushTimer;
  DateTime? _lastCapturedAt;
  String? error;
  bool _flushing = false;

  bool get isOnDuty => _sessionId != null;
  int? get sessionId => _sessionId;
  DateTime? get startTime => _startTime;

  /// Opens a new Duty_Session and starts the foreground service + GPS
  /// stream. Call only after consent has been recorded and
  /// [TrackingPermissionService.requestAll] returned granted. Returns false
  /// (with [error] set) if the server rejects the request, e.g. consent
  /// missing.
  Future<bool> startDuty() async {
    if (isOnDuty) return true;
    error = null;
    try {
      final info = await _trackingApi.startDuty();
      _sessionId = info.sessionId;
      _startTime = info.startTime;

      await FlutterForegroundTask.startService(
        serviceId: 9100,
        notificationTitle: 'Duty active',
        notificationText: 'Your location is being shared with your branch.',
        callback: dutyForegroundTaskCallback,
      );

      _positionSub = Geolocator.getPositionStream(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.medium,
          distanceFilter: kTrackingDistanceFilterMeters,
        ),
      ).listen(_enqueuePosition, onError: (_) {});

      _periodicTimer = Timer.periodic(kTrackingCaptureInterval, (_) => _captureNow());
      _flushTimer = Timer.periodic(kTrackingFlushInterval, (_) => _flushQueue());
      unawaited(_captureNow()); // an immediate first fix, don't wait 5 minutes

      notifyListeners();
      return true;
    } catch (e) {
      error = 'Could not start duty tracking: $e';
      notifyListeners();
      return false;
    }
  }

  /// Stops everything locally (stream, timers, foreground service) first --
  /// that part always succeeds -- then best-effort flushes any remaining
  /// queued points and tells the server the session ended.
  Future<void> endDuty() async {
    final sessionId = _sessionId;
    if (sessionId == null) return;

    _periodicTimer?.cancel();
    _periodicTimer = null;
    _flushTimer?.cancel();
    _flushTimer = null;
    await _positionSub?.cancel();
    _positionSub = null;

    await _flushQueue(); // sessionId is still set, so this can still upload

    if (await FlutterForegroundTask.isRunningService) {
      await FlutterForegroundTask.stopService();
    }

    try {
      await _trackingApi.endDuty(sessionId);
    } catch (_) {
      // Device-side tracking is already fully stopped, which is what
      // matters most; the server session may stay "Active" until a retry.
    }

    _sessionId = null;
    _startTime = null;
    notifyListeners();
  }

  void _enqueuePosition(Position position) => _enqueue(position);

  Future<void> _captureNow() async {
    try {
      final position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(accuracy: LocationAccuracy.medium, timeLimit: Duration(seconds: 20)),
      );
      _enqueue(position);
    } catch (_) {
      // Best-effort -- the next periodic tick or movement-triggered fix tries again.
    }
  }

  void _enqueue(Position position) {
    final now = DateTime.now();
    // The distance-filtered stream and the 5-minute timer can both fire in
    // close succession -- collapse anything within 10s of the last accepted
    // fix instead of queuing near-duplicates.
    if (_lastCapturedAt != null && now.difference(_lastCapturedAt!) < const Duration(seconds: 10)) {
      return;
    }
    _lastCapturedAt = now;
    unawaited(LocationQueueStore.enqueue(LocationPoint(
      lat: position.latitude,
      lng: position.longitude,
      accuracy: position.accuracy,
      speed: position.speed,
      capturedAt: now,
    )));
    unawaited(_flushQueue());
  }

  Future<void> _flushQueue() async {
    if (_flushing || _sessionId == null) return;
    _flushing = true;
    try {
      final queued = await LocationQueueStore.peekAll();
      if (queued.isEmpty) return;
      await _trackingApi.sendPing(sessionId: _sessionId!, points: queued);
      await LocationQueueStore.clear();
    } catch (_) {
      // Offline or a server hiccup -- leave the queue in place; the next
      // successful flush (timer-driven, or triggered by the next capture)
      // retries these same points plus anything added since.
    } finally {
      _flushing = false;
    }
  }

  void _handleAuthChange() {
    if (_authState.status != AuthStatus.authenticated && isOnDuty) {
      unawaited(endDuty());
    }
  }

  @override
  void dispose() {
    _authState.removeListener(_handleAuthChange);
    super.dispose();
  }
}

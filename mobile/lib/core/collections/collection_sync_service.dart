import 'dart:async';

import 'package:flutter/foundation.dart';

import '../api/api_exception.dart';
import '../api/collections_api.dart';
import '../auth/auth_state.dart';
import 'collection_queue_store.dart';

/// Retries every queued-offline payment (see CollectionQueueStore) once
/// there's connectivity again -- a periodic timer while the app is open and
/// the user is logged in, plus an immediate flush right after login/resume
/// so a backlog doesn't sit untouched until the next tick.
class CollectionSyncService extends ChangeNotifier {
  final CollectionsApi _collectionsApi;
  final AuthState _authState;

  CollectionSyncService(this._collectionsApi, this._authState) {
    _authState.addListener(_handleAuthChange);
    _handleAuthChange();
  }

  static const _flushInterval = Duration(seconds: 45);

  Timer? _timer;
  bool _flushing = false;
  int pendingCount = 0;

  Future<void> refreshPendingCount() async {
    pendingCount = (await CollectionQueueStore.peekAll()).length;
    notifyListeners();
  }

  void _handleAuthChange() {
    if (_authState.status == AuthStatus.authenticated) {
      _timer ??= Timer.periodic(_flushInterval, (_) => flushQueue());
      unawaited(flushQueue());
    } else {
      _timer?.cancel();
      _timer = null;
    }
  }

  /// Sends every queued payment in order, oldest first. Stops at the first
  /// network failure -- still offline, no point trying the rest right now --
  /// and leaves it and everything after it queued; a payment the server
  /// actually rejects (already recorded, loan closed, etc.) is dropped from
  /// the queue since retrying it again later will never succeed.
  Future<void> flushQueue() async {
    if (_flushing) return;
    _flushing = true;
    try {
      final queue = await CollectionQueueStore.peekAll();
      if (queue.isEmpty) {
        pendingCount = 0;
        notifyListeners();
        return;
      }

      var sentCount = 0;
      for (final payment in queue) {
        try {
          await _collectionsApi.record(
            loanId: payment.loanId,
            installmentNo: payment.installmentNo,
            amount: payment.amount,
            collectionDate: payment.collectionDate,
            remarks: payment.remarks,
          );
          sentCount++;
        } on ApiException catch (e) {
          if (e.isNetworkError) break; // still offline -- try the rest next time
          sentCount++; // a real rejection -- drop it, don't retry forever
        }
      }

      final remaining = queue.sublist(sentCount);
      await CollectionQueueStore.replaceAll(remaining);
      pendingCount = remaining.length;
      notifyListeners();
    } finally {
      _flushing = false;
    }
  }

  @override
  void dispose() {
    _timer?.cancel();
    _authState.removeListener(_handleAuthChange);
    super.dispose();
  }
}

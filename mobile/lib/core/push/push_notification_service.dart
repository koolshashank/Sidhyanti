import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

import '../../routing/notification_routing.dart';
import '../api/notifications_api.dart';
import '../auth/auth_state.dart';

/// Background/terminated-state messages arrive on a separate isolate, which
/// re-runs main.dart's top-level code but NOT runApp() -- this handler is
/// the only thing that executes. It must be a top-level function (not a
/// class method) and Firebase must be re-initialized here. Showing the
/// system tray notification itself is handled automatically by FCM/Android
/// from the message's `notification` payload -- this just needs to exist so
/// the plugin has somewhere to route background messages.
@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp();
}

/// Wires up push notifications end-to-end: requests permission, registers
/// this device's FCM token with the backend (and re-registers whenever
/// Firebase rotates it), shows a local notification while the app is in
/// the foreground (FCM doesn't surface its own `notification` payload to
/// the system tray while foregrounded), and deep-links on tap -- whether
/// the app was foregrounded, backgrounded, or launched cold from the tap --
/// using the same resolveNotificationRoute() the in-app Notifications list
/// uses.
class PushNotificationService {
  final NotificationsApi _notificationsApi;
  final AuthState _authState;
  final _localNotifications = FlutterLocalNotificationsPlugin();

  static const _channel = AndroidNotificationChannel(
    'high_importance_channel',
    'Important Notifications',
    description: 'Approvals and other time-sensitive alerts',
    importance: Importance.high,
  );

  bool _started = false;

  PushNotificationService(this._notificationsApi, this._authState);

  Future<void> start() async {
    if (_started) return;
    _started = true;

    await _localNotifications
        .resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(_channel);
    await _localNotifications.initialize(
      const InitializationSettings(android: AndroidInitializationSettings('@mipmap/ic_launcher')),
      onDidReceiveNotificationResponse: (response) => _handlePayload(response.payload),
    );

    final messaging = FirebaseMessaging.instance;
    await messaging.requestPermission(alert: true, badge: true, sound: true);

    _authState.addListener(_registerIfLoggedIn);
    _registerIfLoggedIn();
    messaging.onTokenRefresh.listen((_) => _registerIfLoggedIn());

    FirebaseMessaging.onMessage.listen(_showForegroundNotification);
    FirebaseMessaging.onMessageOpenedApp.listen((message) => _handleMessage(message));

    final initialMessage = await messaging.getInitialMessage();
    if (initialMessage != null) _handleMessage(initialMessage);
  }

  Future<void> _registerIfLoggedIn() async {
    if (_authState.currentStaff == null) return;
    try {
      final token = await FirebaseMessaging.instance.getToken();
      if (token != null) await _notificationsApi.registerDevice(token);
    } catch (e) {
      debugPrint('Push token registration failed: $e');
    }
  }

  void _showForegroundNotification(RemoteMessage message) {
    final title = message.notification?.title ?? message.data['title'] as String?;
    final body = message.notification?.body ?? message.data['body'] as String?;
    if (title == null) return;
    _localNotifications.show(
      message.hashCode,
      title,
      body,
      NotificationDetails(
        android: AndroidNotificationDetails(_channel.id, _channel.name, channelDescription: _channel.description, importance: Importance.high),
      ),
      payload: _encodePayload(message.data),
    );
  }

  void _handleMessage(RemoteMessage message) {
    _navigate(message.data['related_entity_type'] as String?, message.data['related_entity_id'] as String?, (message.data['title'] as String?) ?? '');
  }

  void _handlePayload(String? payload) {
    if (payload == null) return;
    final parts = payload.split('|');
    if (parts.length != 3) return;
    _navigate(parts[0].isEmpty ? null : parts[0], parts[1].isEmpty ? null : parts[1], parts[2]);
  }

  void _navigate(String? relatedEntityType, String? relatedEntityIdStr, String title) {
    openNotificationRouteGlobally(
      relatedEntityType: relatedEntityType,
      relatedEntityId: relatedEntityIdStr != null ? int.tryParse(relatedEntityIdStr) : null,
      title: title,
    );
  }

  String _encodePayload(Map<String, dynamic> data) {
    final type = data['related_entity_type'] as String? ?? '';
    final id = data['related_entity_id'] as String? ?? '';
    final title = data['title'] as String? ?? '';
    return '$type|$id|$title';
  }
}

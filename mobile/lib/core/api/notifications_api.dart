import '../models/notification_item.dart';
import 'api_client.dart';
import 'endpoints.dart';

class NotificationList {
  final List<NotificationItem> items;
  final int unreadCount;

  NotificationList({required this.items, required this.unreadCount});
}

class NotificationsApi {
  final ApiClient _client;

  NotificationsApi(this._client);

  /// [relatedEntityType] optionally scopes this to one category -- e.g.
  /// 'Announcement' for the dedicated Announcements feed -- instead of
  /// every notification (the default, used by the bell icon).
  Future<NotificationList> list({String? relatedEntityType}) async {
    final data = await _client.get(Endpoints.notificationsList, query: {
      if (relatedEntityType != null) 'related_entity_type': relatedEntityType,
    });
    return NotificationList(
      items: (data['items'] as List<dynamic>).map((e) => NotificationItem.fromJson(e as Map<String, dynamic>)).toList(),
      unreadCount: data['unread_count'] as int? ?? 0,
    );
  }

  Future<int> markRead({int? id, bool all = false}) async {
    final data = await _client.post(Endpoints.notificationsMarkRead, data: {
      if (all) 'all': true,
      'id': ?id,
    });
    return data['unread_count'] as int? ?? 0;
  }

  Future<int> delete({int? id, bool all = false}) async {
    final data = await _client.post(Endpoints.notificationsDelete, data: {
      if (all) 'all': true,
      'id': ?id,
    });
    return data['unread_count'] as int? ?? 0;
  }

  Future<void> registerDevice(String fcmToken) async {
    await _client.post(Endpoints.notificationsRegisterDevice, data: {'fcm_token': fcmToken});
  }
}

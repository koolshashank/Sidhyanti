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

  Future<NotificationList> list() async {
    final data = await _client.get(Endpoints.notificationsList);
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
}

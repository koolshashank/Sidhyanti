import 'package:flutter/foundation.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/notifications_api.dart';
import '../../core/models/notification_item.dart';

/// Registered as a single app-wide instance (see main.dart) so the dashboard
/// bell badge and the Notifications screen always agree on the unread count
/// -- marking something read on the screen updates the badge immediately,
/// with no polling or manual refresh-on-return needed.
class NotificationsViewModel extends ChangeNotifier {
  final NotificationsApi _api;

  NotificationsViewModel(this._api);

  bool loading = false;
  String? error;
  List<NotificationItem> items = [];
  int unreadCount = 0;

  Future<void> load() async {
    loading = true;
    error = null;
    notifyListeners();
    try {
      final result = await _api.list();
      items = result.items;
      unreadCount = result.unreadCount;
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      loading = false;
      notifyListeners();
    }
  }

  Future<void> markRead(int notificationId) async {
    final index = items.indexWhere((n) => n.notificationId == notificationId);
    if (index == -1 || items[index].isRead) return;
    try {
      unreadCount = await _api.markRead(id: notificationId);
      final n = items[index];
      items[index] = NotificationItem(
        notificationId: n.notificationId,
        title: n.title,
        message: n.message,
        typeName: n.typeName,
        isRead: true,
        relatedEntityType: n.relatedEntityType,
        relatedEntityId: n.relatedEntityId,
        createdAt: n.createdAt,
      );
      notifyListeners();
    } on ApiException {
      // Leave the item as-is; the user can retry by tapping again.
    }
  }

  Future<void> dismiss(int notificationId) async {
    final index = items.indexWhere((n) => n.notificationId == notificationId);
    if (index == -1) return;
    final removed = items[index];
    items.removeAt(index);
    if (!removed.isRead && unreadCount > 0) unreadCount--;
    notifyListeners();
    try {
      unreadCount = await _api.delete(id: notificationId);
    } on ApiException {
      // Keep the optimistic removal -- a subsequent load() will reconcile.
    }
  }

  Future<void> clearAll() async {
    if (items.isEmpty) return;
    items = [];
    unreadCount = 0;
    notifyListeners();
    try {
      await _api.delete(all: true);
    } on ApiException {
      // Best-effort; a subsequent load() will reconcile.
    }
  }

  Future<void> markAllRead() async {
    if (unreadCount == 0) return;
    try {
      unreadCount = await _api.markRead(all: true);
      items = items
          .map((n) => NotificationItem(
                notificationId: n.notificationId,
                title: n.title,
                message: n.message,
                typeName: n.typeName,
                isRead: true,
                relatedEntityType: n.relatedEntityType,
                relatedEntityId: n.relatedEntityId,
                createdAt: n.createdAt,
              ))
          .toList();
      notifyListeners();
    } on ApiException {
      // Best-effort; a subsequent load() will reconcile.
    }
  }
}

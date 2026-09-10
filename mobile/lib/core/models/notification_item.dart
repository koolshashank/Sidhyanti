class NotificationItem {
  final int notificationId;
  final String title;
  final String? message;
  final String? typeName;
  final bool isRead;
  final String? relatedEntityType;
  final int? relatedEntityId;
  final DateTime? createdAt;

  NotificationItem({
    required this.notificationId,
    required this.title,
    required this.message,
    required this.typeName,
    required this.isRead,
    required this.relatedEntityType,
    required this.relatedEntityId,
    required this.createdAt,
  });

  factory NotificationItem.fromJson(Map<String, dynamic> json) => NotificationItem(
        notificationId: json['notification_id'] as int,
        title: json['title'] as String? ?? '',
        message: json['message'] as String?,
        typeName: json['type_name'] as String?,
        isRead: json['is_read'] as bool? ?? false,
        relatedEntityType: json['related_entity_type'] as String?,
        relatedEntityId: json['related_entity_id'] as int?,
        createdAt: json['created_at'] != null ? DateTime.tryParse(json['created_at'] as String) : null,
      );
}

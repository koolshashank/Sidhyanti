<?php
/**
 * Single entry point for raising a Notification -- inserts the row (so it
 * always shows in the in-app Notifications list, same as before) and sends
 * a push alongside it. Every "INSERT INTO Notification" across the app
 * should go through this instead, so nothing new gets added later without
 * also pushing.
 */

require_once __DIR__ . '/push_notification_helper.php';

function notify_staff(PDO $pdo, int $staffId, string $title, string $message, int $notificationTypeId, string $relatedEntityType, ?int $relatedEntityId): void {
    $pdo->prepare("
      INSERT INTO Notification (StaffID, Title, Message, NotificationTypeID, RelatedEntityType, RelatedEntityID, IsRead, CreatedAt)
      VALUES (?, ?, ?, ?, ?, ?, 0, GETDATE())
    ")->execute([$staffId, $title, $message, $notificationTypeId, $relatedEntityType, $relatedEntityId]);

    send_push_notification($pdo, $staffId, $title, $message, $relatedEntityType, $relatedEntityId);
}

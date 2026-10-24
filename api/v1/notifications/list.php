<?php
/**
 * GET /api/v1/notifications/list.php
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();

// Optional filter -- e.g. ?related_entity_type=Announcement for the
// dedicated Announcements feed, reusing this same list/unread-count
// machinery instead of a parallel endpoint. Omitted, this is the normal
// all-notifications bell icon behavior, unchanged.
$relatedEntityType = trim((string)($_GET['related_entity_type'] ?? ''));
$typeFilterSql = $relatedEntityType !== '' ? 'AND n.RelatedEntityType = ?' : '';

$stmt = $pdo->prepare("
  SELECT TOP 100 n.*, nt.TypeName FROM Notification n
  LEFT JOIN Notification_Type_Master nt ON n.NotificationTypeID = nt.TypeID
  WHERE n.StaffID = ? $typeFilterSql ORDER BY n.IsRead ASC, n.CreatedAt DESC
");
$stmt->execute($relatedEntityType !== '' ? [(int)$claims['StaffID'], $relatedEntityType] : [(int)$claims['StaffID']]);
$rows = $stmt->fetchAll();

$unread = 0;
$items = array_map(function ($n) use (&$unread) {
    $isRead = to_bool($n['IsRead']);
    if (!$isRead) {
        $unread++;
    }
    return [
        'notification_id'      => (int)$n['NotificationID'],
        'title'                 => $n['Title'],
        'message'               => $n['Message'],
        'type_name'             => $n['TypeName'],
        'is_read'               => $isRead,
        'related_entity_type'   => $n['RelatedEntityType'],
        'related_entity_id'     => $n['RelatedEntityID'] !== null ? (int)$n['RelatedEntityID'] : null,
        'created_at'            => to_iso_date($n['CreatedAt']),
    ];
}, $rows);

json_response(true, [
    'items'        => $items,
    'unread_count' => $unread,
]);

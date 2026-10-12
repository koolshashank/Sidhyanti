<?php
/**
 * POST /api/v1/notifications/delete.php
 * Body: { id? } (dismiss one) or { all: true } (clear every notification
 * for the current staff). At least one is required.
 */

require_once __DIR__ . '/../../includes/api_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();
$body = get_json_body();
$staffId = (int)$claims['StaffID'];

if (!empty($body['all'])) {
    $pdo->prepare("DELETE FROM Notification WHERE StaffID=?")->execute([$staffId]);
} elseif (!empty($body['id'])) {
    $pdo->prepare("DELETE FROM Notification WHERE NotificationID=? AND StaffID=?")->execute([(int)$body['id'], $staffId]);
} else {
    json_response(false, null, 'id or all is required', 422);
}

$unread = $pdo->prepare("SELECT COUNT(*) FROM Notification WHERE StaffID=? AND IsRead=0");
$unread->execute([$staffId]);

json_response(true, ['unread_count' => (int)$unread->fetchColumn()]);

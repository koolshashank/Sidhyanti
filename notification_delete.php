<?php
/**
 * POST notification_delete.php -- AJAX endpoint backing the header
 * notification dropdown's per-item dismiss and notifications.php's
 * per-item/"Clear all" delete. Body: id=<NotificationID> (delete one) OR
 * all=1 (delete every notification for the current staff). Returns
 * {success, unread_count}.
 */
require_once __DIR__ . '/includes/functions.php';
require_login();

header('Content-Type: application/json');
$sid = current_staff()['StaffID'];

if (isset($_POST['all'])) {
    $pdo->prepare("DELETE FROM Notification WHERE StaffID=?")->execute([$sid]);
} elseif (isset($_POST['id'])) {
    $pdo->prepare("DELETE FROM Notification WHERE NotificationID=? AND StaffID=?")->execute([(int)$_POST['id'], $sid]);
} else {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'id or all is required']);
    exit;
}

$unread = $pdo->prepare("SELECT COUNT(*) FROM Notification WHERE StaffID=? AND IsRead=0");
$unread->execute([$sid]);

echo json_encode(['success' => true, 'unread_count' => (int)$unread->fetchColumn()]);

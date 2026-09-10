<?php
/**
 * POST notification_mark_read.php -- AJAX endpoint backing the header
 * notification dropdown (includes/header.php + footer.php's script).
 * Body: id=<NotificationID> (mark one) OR all=1 (mark every unread
 * notification for the current staff). Returns {success, unread_count}.
 */
require_once __DIR__ . '/includes/functions.php';
require_login();

header('Content-Type: application/json');
$sid = current_staff()['StaffID'];

if (isset($_POST['all'])) {
    $pdo->prepare("UPDATE Notification SET IsRead=1 WHERE StaffID=? AND IsRead=0")->execute([$sid]);
} elseif (isset($_POST['id'])) {
    $pdo->prepare("UPDATE Notification SET IsRead=1 WHERE NotificationID=? AND StaffID=?")->execute([(int)$_POST['id'], $sid]);
} else {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'id or all is required']);
    exit;
}

$unread = $pdo->prepare("SELECT COUNT(*) FROM Notification WHERE StaffID=? AND IsRead=0");
$unread->execute([$sid]);

echo json_encode(['success' => true, 'unread_count' => (int)$unread->fetchColumn()]);

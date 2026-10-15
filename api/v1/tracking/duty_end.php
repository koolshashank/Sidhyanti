<?php
/**
 * POST /api/v1/tracking/duty_end.php
 * { "session_id": 123 }
 *
 * Closes the caller's own Active duty session. Already-ended sessions are
 * accepted as a no-op (the mobile app may retry this call after a flaky
 * connection without erroring out).
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();
$staffId = (int)$claims['StaffID'];
$body = get_json_body();
$sessionId = (int)($body['session_id'] ?? 0);
if ($sessionId <= 0) {
    json_response(false, null, 'session_id is required', 422);
}

$stmt = $pdo->prepare("SELECT StaffID, Status, EndTime FROM Duty_Session WHERE SessionID = ?");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();
if (!$session) {
    json_response(false, null, 'Duty session not found', 404);
}
if ((int)$session['StaffID'] !== $staffId) {
    json_response(false, null, 'Forbidden', 403);
}

if ($session['Status'] === 'Active') {
    $pdo->prepare("UPDATE Duty_Session SET Status = 'Ended', EndTime = GETDATE() WHERE SessionID = ?")
        ->execute([$sessionId]);
    $endTimeStmt = $pdo->prepare("SELECT EndTime FROM Duty_Session WHERE SessionID = ?");
    $endTimeStmt->execute([$sessionId]);
    $endTime = $endTimeStmt->fetchColumn();
} else {
    $endTime = $session['EndTime'];
}

json_response(true, [
    'session_id' => $sessionId,
    'end_time'   => to_iso_date($endTime),
]);

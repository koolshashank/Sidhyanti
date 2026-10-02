<?php
/**
 * POST /api/v1/hr/checkout.php
 * Body: { latitude?, longitude? }
 * Marks today's check-out for the caller. Requires a check-in to already
 * exist for today.
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth(false);
$staffId = (int)$claims['StaffID'];
$body = get_json_body();
$lat = isset($body['latitude']) ? (float)$body['latitude'] : null;
$lng = isset($body['longitude']) ? (float)$body['longitude'] : null;

$stmt = $pdo->prepare("SELECT AttendanceID, CheckInAt, CheckOutAt FROM Attendance WHERE StaffID = ? AND AttendanceDate = CAST(GETDATE() AS DATE)");
$stmt->execute([$staffId]);
$existing = $stmt->fetch();

if (!$existing || $existing['CheckInAt'] === null) {
    json_response(false, null, 'Please check in first', 422);
}
if ($existing['CheckOutAt'] !== null) {
    json_response(true, ['check_out_at' => to_iso_date($existing['CheckOutAt'])], 'Already checked out today');
}

$pdo->prepare("UPDATE Attendance SET CheckOutAt = GETDATE(), CheckOutLatitude = ?, CheckOutLongitude = ? WHERE AttendanceID = ?")
    ->execute([$lat, $lng, $existing['AttendanceID']]);

json_response(true, null, 'Checked out');

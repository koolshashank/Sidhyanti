<?php
/**
 * POST /api/v1/hr/checkin.php
 * Body: { latitude?, longitude? }
 * Marks today's check-in for the caller. Idempotent -- calling it again the
 * same day just leaves the original CheckInAt time as-is (a re-tap doesn't
 * overwrite the real check-in moment).
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();
$staffId = (int)$claims['StaffID'];
$body = get_json_body();
$lat = isset($body['latitude']) ? (float)$body['latitude'] : null;
$lng = isset($body['longitude']) ? (float)$body['longitude'] : null;

$stmt = $pdo->prepare("SELECT AttendanceID, CheckInAt FROM Attendance WHERE StaffID = ? AND AttendanceDate = CAST(GETDATE() AS DATE)");
$stmt->execute([$staffId]);
$existing = $stmt->fetch();

if ($existing && $existing['CheckInAt'] !== null) {
    json_response(true, ['check_in_at' => to_iso_date($existing['CheckInAt'])], 'Already checked in today');
}

if ($existing) {
    $pdo->prepare("UPDATE Attendance SET CheckInAt = GETDATE(), CheckInLatitude = ?, CheckInLongitude = ? WHERE AttendanceID = ?")
        ->execute([$lat, $lng, $existing['AttendanceID']]);
} else {
    $pdo->prepare("INSERT INTO Attendance (StaffID, AttendanceDate, CheckInAt, CheckInLatitude, CheckInLongitude) VALUES (?, CAST(GETDATE() AS DATE), GETDATE(), ?, ?)")
        ->execute([$staffId, $lat, $lng]);
}

json_response(true, null, 'Checked in');

<?php
/**
 * GET /api/v1/hr/attendance_today.php
 * Returns the caller's own attendance row for today (or nulls if not
 * checked in yet).
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth(false);
$staffId = (int)$claims['StaffID'];

$stmt = $pdo->prepare("SELECT * FROM Attendance WHERE StaffID = ? AND AttendanceDate = CAST(GETDATE() AS DATE)");
$stmt->execute([$staffId]);
$row = $stmt->fetch();

json_response(true, [
    'checked_in'  => $row !== false && $row['CheckInAt'] !== null,
    'checked_out' => $row !== false && $row['CheckOutAt'] !== null,
    'check_in_at'  => $row ? to_iso_date($row['CheckInAt']) : null,
    'check_out_at' => $row ? to_iso_date($row['CheckOutAt']) : null,
]);

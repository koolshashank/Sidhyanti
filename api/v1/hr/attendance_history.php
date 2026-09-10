<?php
/**
 * GET /api/v1/hr/attendance_history.php
 * Returns the caller's own attendance rows for the last 14 days, most
 * recent first.
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();
$staffId = (int)$claims['StaffID'];

$stmt = $pdo->prepare("
  SELECT TOP 14 AttendanceDate, CheckInAt, CheckOutAt
  FROM Attendance
  WHERE StaffID = ?
  ORDER BY AttendanceDate DESC
");
$stmt->execute([$staffId]);
$rows = $stmt->fetchAll();

json_response(true, [
    'items' => array_map(function ($r) {
        return [
            'date'         => to_iso_date($r['AttendanceDate']),
            'check_in_at'  => to_iso_date($r['CheckInAt']),
            'check_out_at' => to_iso_date($r['CheckOutAt']),
        ];
    }, $rows),
]);

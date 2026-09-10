<?php
/**
 * POST /api/v1/hr/leave_request_create.php
 * Body: { leave_type, from_date, to_date, reason? }
 */

require_once __DIR__ . '/../../includes/api_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();
$staffId = (int)$claims['StaffID'];
$body = get_json_body();

$leaveType = trim((string)($body['leave_type'] ?? ''));
$fromDate = trim((string)($body['from_date'] ?? ''));
$toDate = trim((string)($body['to_date'] ?? ''));

if ($leaveType === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
    json_response(false, null, 'leave_type, from_date and to_date (YYYY-MM-DD) are required', 422);
}
if ($toDate < $fromDate) {
    json_response(false, null, 'to_date cannot be before from_date', 422);
}

$newId = insert_and_get_id($pdo, "INSERT INTO Leave_Request (StaffID, LeaveType, FromDate, ToDate, Reason, Status)
    VALUES (?,?,?,?,?, 'Pending')", [
    $staffId,
    $leaveType,
    $fromDate,
    $toDate,
    $body['reason'] ?? null,
], 'LeaveRequestID');

json_response(true, ['leave_request_id' => $newId], 'Leave request submitted');

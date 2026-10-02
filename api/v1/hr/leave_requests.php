<?php
/**
 * GET /api/v1/hr/leave_requests.php
 * Returns the caller's own leave requests, most recent first.
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

// Bypasses the attendance gate: this loads alongside attendance_today.php on
// the same HR screen a staff member uses to check in, so it can't 403 and
// take the whole screen (and its Check-In button) down with it.
$claims = require_api_auth(false);
$staffId = (int)$claims['StaffID'];

$stmt = $pdo->prepare("
  SELECT lr.LeaveRequestID, lr.LeaveType, lr.FromDate, lr.ToDate, lr.Reason, lr.Status,
         lr.RemarksByApprover, lr.CreatedAt, app.FullName AS ApproverName
  FROM Leave_Request lr
  LEFT JOIN Staff app ON lr.ApprovedByStaffID = app.StaffID
  WHERE lr.StaffID = ?
  ORDER BY lr.LeaveRequestID DESC
");
$stmt->execute([$staffId]);
$rows = $stmt->fetchAll();

json_response(true, [
    'items' => array_map(function ($r) {
        return [
            'leave_request_id' => (int)$r['LeaveRequestID'],
            'leave_type'       => $r['LeaveType'],
            'from_date'        => to_iso_date($r['FromDate']),
            'to_date'          => to_iso_date($r['ToDate']),
            'reason'           => $r['Reason'],
            'status'           => $r['Status'],
            'remarks'          => $r['RemarksByApprover'],
            'approver_name'    => $r['ApproverName'],
            'created_at'       => to_iso_date($r['CreatedAt']),
        ];
    }, $rows),
]);

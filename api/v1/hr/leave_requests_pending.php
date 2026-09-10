<?php
/**
 * GET /api/v1/hr/leave_requests_pending.php
 * Reviewer-facing queue: every Pending leave request from staff in the
 * caller's branch (Admin sees every branch). Distinct from
 * leave_requests.php, which returns only the caller's own requests.
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';
require_once __DIR__ . '/../../../includes/permission_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();

if (!role_has_permission($pdo, $claims['RoleID'] ?? null, 'approve_leave_request')) {
    json_response(false, null, 'You do not have permission to review leave requests', 403);
}

[$whereScope, $scopeParams] = leave_scope_where($claims, 'st');
$where = $whereScope !== '' ? "AND $whereScope" : '';

$stmt = $pdo->prepare("
  SELECT lr.LeaveRequestID, lr.LeaveType, lr.FromDate, lr.ToDate, lr.Reason, lr.Status, lr.CreatedAt,
         st.StaffID, st.FullName AS StaffName
  FROM Leave_Request lr
  JOIN Staff st ON lr.StaffID = st.StaffID
  WHERE lr.Status = 'Pending' $where
  ORDER BY lr.CreatedAt ASC
");
$stmt->execute($scopeParams);
$rows = $stmt->fetchAll();

json_response(true, [
    'items' => array_map(function ($r) {
        return [
            'leave_request_id' => (int)$r['LeaveRequestID'],
            'staff_id'         => (int)$r['StaffID'],
            'staff_name'       => $r['StaffName'],
            'leave_type'       => $r['LeaveType'],
            'from_date'        => to_iso_date($r['FromDate']),
            'to_date'          => to_iso_date($r['ToDate']),
            'reason'           => $r['Reason'],
            'status'           => $r['Status'],
            'created_at'       => to_iso_date($r['CreatedAt']),
        ];
    }, $rows),
]);

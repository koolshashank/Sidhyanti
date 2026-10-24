<?php
/**
 * GET /api/v1/hr/salary_advance_requests_pending.php
 * Reviewer-facing queue: every Pending salary advance request, company-wide.
 * Admin/HR only (there's no separate HR role in this system yet, so this
 * mirrors every other hardcoded "Admin only" gate -- see is_admin_or_super).
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();

if (($claims['RoleName'] ?? '') !== 'Admin') {
    json_response(false, null, 'You do not have permission to review salary advance requests', 403);
}

$stmt = $pdo->prepare("
  SELECT sar.RequestID, sar.RequestType, sar.Amount, sar.Reason, sar.Status, sar.CreatedAt,
         st.StaffID, st.FullName AS StaffName, b.BranchName
  FROM Salary_Advance_Request sar
  JOIN Staff st ON sar.StaffID = st.StaffID
  LEFT JOIN Branch b ON st.BranchID = b.BranchID
  WHERE sar.Status = 'Pending'
  ORDER BY sar.CreatedAt ASC
");
$stmt->execute();
$rows = $stmt->fetchAll();

json_response(true, [
    'items' => array_map(function ($r) {
        return [
            'request_id'   => (int)$r['RequestID'],
            'staff_id'     => (int)$r['StaffID'],
            'staff_name'   => $r['StaffName'],
            'branch_name'  => $r['BranchName'],
            'request_type' => $r['RequestType'],
            'amount'       => (float)$r['Amount'],
            'reason'       => $r['Reason'],
            'status'       => $r['Status'],
            'created_at'   => to_iso_date($r['CreatedAt']),
        ];
    }, $rows),
]);

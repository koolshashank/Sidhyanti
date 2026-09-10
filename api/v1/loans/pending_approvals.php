<?php
/**
 * GET /api/v1/loans/pending_approvals.php?type=Proposal|Preclose
 * Reviewer-facing queue, branch-scoped (Admin sees every branch).
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';
require_once __DIR__ . '/../../../includes/permission_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();
$type = trim((string)($_GET['type'] ?? ''));
if (!in_array($type, ['Proposal', 'Preclose'], true)) {
    json_response(false, null, 'type must be Proposal or Preclose', 422);
}

$permissionKey = $type === 'Preclose' ? 'approve_loan_preclose' : 'approve_loan_proposal';
if (!role_has_permission($pdo, $claims['RoleID'] ?? null, $permissionKey)) {
    json_response(false, null, 'You do not have permission to review these requests', 403);
}

[$whereScope, $scopeParams] = loan_scope_where($claims, 'l');
$where = $whereScope !== '' ? "AND $whereScope" : '';

$stmt = $pdo->prepare("
  SELECT la.LoanApprovalID, la.LoanID, la.RequestRemarks, la.CreatedAt,
         l.LoanNo, m.FirstName, m.LastName, req.FullName AS RequestedByName
  FROM Loan_Approval la
  JOIN Loan l ON la.LoanID = l.LoanID
  JOIN Member m ON l.MemberID = m.MemberID
  LEFT JOIN Staff req ON la.RequestedByStaffID = req.StaffID
  WHERE la.RequestType = ? AND la.Status = 'Pending' $where
  ORDER BY la.CreatedAt ASC
");
$stmt->execute(array_merge([$type], $scopeParams));
$rows = $stmt->fetchAll();

json_response(true, [
    'items' => array_map(function ($r) {
        return [
            'loan_approval_id'  => (int)$r['LoanApprovalID'],
            'loan_id'           => (int)$r['LoanID'],
            'loan_no'           => $r['LoanNo'],
            'member_name'       => trim($r['FirstName'] . ' ' . $r['LastName']),
            'request_remarks'   => $r['RequestRemarks'],
            'requested_by_name' => $r['RequestedByName'],
            'created_at'        => to_iso_date($r['CreatedAt']),
        ];
    }, $rows),
]);

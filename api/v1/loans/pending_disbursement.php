<?php
/**
 * GET /api/v1/loans/pending_disbursement.php
 * Branch-scoped list of loans currently at the 'Approved' stage (i.e.
 * ready to disburse but not yet disbursed).
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';
require_once __DIR__ . '/../../../includes/permission_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();

if (!role_has_permission($pdo, $claims['RoleID'] ?? null, 'disburse_loan')) {
    json_response(false, null, 'You do not have permission to disburse loans', 403);
}

[$whereScope, $scopeParams] = loan_scope_where($claims, 'l');
$where = $whereScope !== '' ? "AND $whereScope" : '';

$stmt = $pdo->prepare("
  SELECT l.LoanID, l.LoanNo, l.LoanAmountSanctioned, l.LoanAmountApplied, l.ApprovalDate,
         m.FirstName, m.LastName, c.CenterName, officer.FullName AS OfficerName
  FROM Loan l
  JOIN Member m ON l.MemberID = m.MemberID
  LEFT JOIN Center c ON l.CenterID = c.CenterID
  LEFT JOIN Staff officer ON l.LoanOfficerStaffID = officer.StaffID
  LEFT JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
  WHERE stg.StageName = 'Approved' $where
  ORDER BY l.ApprovalDate ASC
");
$stmt->execute($scopeParams);
$rows = $stmt->fetchAll();

json_response(true, [
    'items' => array_map(function ($r) {
        return [
            'loan_id'      => (int)$r['LoanID'],
            'loan_no'      => $r['LoanNo'],
            'member_name'  => trim($r['FirstName'] . ' ' . $r['LastName']),
            'center_name'  => $r['CenterName'],
            'officer_name' => $r['OfficerName'],
            'loan_amount'  => to_money($r['LoanAmountSanctioned'] ?: $r['LoanAmountApplied']),
            'approval_date' => to_iso_date($r['ApprovalDate']),
        ];
    }, $rows),
]);

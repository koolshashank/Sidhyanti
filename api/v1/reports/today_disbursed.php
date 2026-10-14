<?php
/**
 * GET /api/v1/reports/today_disbursed.php
 * Loans disbursed today, scoped by role via loan_scope_where().
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();

[$scopeSql, $scopeParams] = loan_scope_where($claims, 'l');
$where = $scopeSql !== '' ? "AND $scopeSql" : '';

$stmt = $pdo->prepare("
  SELECT l.LoanID, l.LoanNo, m.FirstName, m.LastName, b.BranchName, c.CenterName,
         officer.FullName AS OfficerName, COALESCE(l.LoanAmountSanctioned, l.LoanAmountApplied) AS Amount,
         l.DisbursementMode
  FROM Loan l
  JOIN Member m ON l.MemberID = m.MemberID
  LEFT JOIN Branch b ON l.BranchID = b.BranchID
  LEFT JOIN Center c ON l.CenterID = c.CenterID
  LEFT JOIN Staff officer ON l.LoanOfficerStaffID = officer.StaffID
  WHERE CAST(l.DisbursementDate AS DATE) = CAST(GETDATE() AS DATE) $where
  ORDER BY b.BranchName, c.CenterName
");
$stmt->execute($scopeParams);
$rows = $stmt->fetchAll();

json_response(true, [
    'items' => array_map(function ($r) {
        return [
            'loan_id' => (int)$r['LoanID'],
            'loan_no' => $r['LoanNo'],
            'member_name' => trim($r['FirstName'] . ' ' . $r['LastName']),
            'branch_name' => $r['BranchName'],
            'center_name' => $r['CenterName'],
            'officer_name' => $r['OfficerName'],
            'amount' => to_money($r['Amount']),
            'disbursement_mode' => $r['DisbursementMode'],
        ];
    }, $rows),
]);

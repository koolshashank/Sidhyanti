<?php
/**
 * GET /api/v1/reports/arrears_clients.php
 * Every currently-in-arrears loan -- broken installment count, which
 * installment it broke from, and total arrear amount. Scoped by role via
 * loan_scope_where().
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
  ;WITH Broken AS (
    SELECT rs.LoanID, MIN(rs.InstallmentNo) AS FirstBrokenInstallment,
           COUNT(*) AS BrokenCount, SUM(rs.PrincipalDue) AS BrokenAmount, MIN(rs.DueDate) AS FirstBrokenDate
    FROM Repayment_Schedule rs
    WHERE rs.IsPaid = 0 AND rs.DueDate < CAST(GETDATE() AS DATE)
    GROUP BY rs.LoanID
  )
  SELECT l.LoanID, l.LoanNo, l.LoanCycle, m.FirstName, m.LastName, m.MobileNo, b.BranchName, c.CenterName,
         officer.FullName AS OfficerName, br.FirstBrokenInstallment, br.BrokenCount, br.BrokenAmount,
         DATEDIFF(DAY, br.FirstBrokenDate, CAST(GETDATE() AS DATE)) AS DaysOverdue
  FROM Broken br
  JOIN Loan l ON br.LoanID = l.LoanID
  JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
  JOIN Member m ON l.MemberID = m.MemberID
  LEFT JOIN Branch b ON l.BranchID = b.BranchID
  LEFT JOIN Center c ON l.CenterID = c.CenterID
  LEFT JOIN Staff officer ON l.LoanOfficerStaffID = officer.StaffID
  WHERE stg.StageName IN ('Active', 'Disbursed') $where
  ORDER BY br.BrokenAmount DESC
");
$stmt->execute($scopeParams);
$rows = $stmt->fetchAll();

json_response(true, [
    'items' => array_map(function ($r) {
        return [
            'loan_id' => (int)$r['LoanID'],
            'loan_no' => $r['LoanNo'],
            'loan_cycle' => $r['LoanCycle'] !== null ? (int)$r['LoanCycle'] : null,
            'member_name' => trim($r['FirstName'] . ' ' . $r['LastName']),
            'mobile_no' => $r['MobileNo'],
            'branch_name' => $r['BranchName'],
            'center_name' => $r['CenterName'],
            'officer_name' => $r['OfficerName'],
            'first_broken_installment' => (int)$r['FirstBrokenInstallment'],
            'broken_count' => (int)$r['BrokenCount'],
            'broken_amount' => to_money($r['BrokenAmount']),
            'days_overdue' => (int)$r['DaysOverdue'],
        ];
    }, $rows),
]);

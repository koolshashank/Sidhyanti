<?php
/**
 * GET /api/v1/reports/npa_loans.php
 * Loans 90+ days overdue on their oldest unpaid installment -- same "PAR
 * 90+" bucket definition portfolio.php already uses for the web dashboard's
 * aggregate stat, here as a per-loan worklist so a Branch Manager can
 * request a write-off (see api/v1/loans/writeoff_request.php). Scoped by
 * role via loan_scope_where().
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
  ;WITH UnpaidAgg AS (
    SELECT rs.LoanID, MIN(rs.DueDate) AS OldestUnpaidDue, SUM(rs.PrincipalDue) AS Outstanding
    FROM Repayment_Schedule rs WHERE rs.IsPaid = 0 GROUP BY rs.LoanID
  )
  SELECT l.LoanID, l.LoanNo, l.LoanCycle, m.FirstName, m.LastName, m.MobileNo, b.BranchName, c.CenterName,
         officer.FullName AS OfficerName, u.Outstanding,
         DATEDIFF(DAY, u.OldestUnpaidDue, CAST(GETDATE() AS DATE)) AS DaysOverdue,
         (SELECT TOP 1 1 FROM Loan_WriteOff_Request wr WHERE wr.LoanID = l.LoanID AND wr.Status = 'Pending') AS HasPendingWriteOff
  FROM Loan l
  JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
  JOIN Member m ON l.MemberID = m.MemberID
  JOIN UnpaidAgg u ON u.LoanID = l.LoanID
  LEFT JOIN Branch b ON l.BranchID = b.BranchID
  LEFT JOIN Center c ON l.CenterID = c.CenterID
  LEFT JOIN Staff officer ON l.LoanOfficerStaffID = officer.StaffID
  WHERE stg.StageName = 'Active' AND DATEDIFF(DAY, u.OldestUnpaidDue, CAST(GETDATE() AS DATE)) > 90 $where
  ORDER BY u.Outstanding DESC
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
            'outstanding' => to_money($r['Outstanding']),
            'days_overdue' => (int)$r['DaysOverdue'],
            'has_pending_writeoff' => $r['HasPendingWriteOff'] !== null,
        ];
    }, $rows),
]);

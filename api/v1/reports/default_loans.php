<?php
/**
 * GET /api/v1/reports/default_loans.php
 * Loans whose full tenure has run out (last installment's due date is in
 * the past) but are still Active/Disbursed with principal+interest still
 * outstanding. Scoped by role via loan_scope_where().
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
  ;WITH Matured AS (
    SELECT rs.LoanID, MAX(rs.DueDate) AS LastDueDate,
           SUM(CASE WHEN rs.IsPaid = 0 THEN rs.PrincipalDue + rs.InterestDue ELSE 0 END) AS Outstanding
    FROM Repayment_Schedule rs
    GROUP BY rs.LoanID
    HAVING MAX(rs.DueDate) < CAST(GETDATE() AS DATE)
       AND SUM(CASE WHEN rs.IsPaid = 0 THEN rs.PrincipalDue + rs.InterestDue ELSE 0 END) > 0
  )
  SELECT l.LoanID, l.LoanNo, l.LoanCycle, m.FirstName, m.LastName, m.MobileNo, b.BranchName, c.CenterName,
         officer.FullName AS OfficerName, mt.LastDueDate, mt.Outstanding,
         DATEDIFF(DAY, mt.LastDueDate, CAST(GETDATE() AS DATE)) AS DaysPastTenure
  FROM Matured mt
  JOIN Loan l ON mt.LoanID = l.LoanID
  JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
  JOIN Member m ON l.MemberID = m.MemberID
  LEFT JOIN Branch b ON l.BranchID = b.BranchID
  LEFT JOIN Center c ON l.CenterID = c.CenterID
  LEFT JOIN Staff officer ON l.LoanOfficerStaffID = officer.StaffID
  WHERE stg.StageName IN ('Active', 'Disbursed') $where
  ORDER BY mt.Outstanding DESC
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
            'last_due_date' => to_iso_date($r['LastDueDate']),
            'outstanding' => to_money($r['Outstanding']),
            'days_past_tenure' => (int)$r['DaysPastTenure'],
        ];
    }, $rows),
]);

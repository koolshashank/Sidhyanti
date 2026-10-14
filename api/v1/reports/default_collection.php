<?php
/**
 * GET /api/v1/reports/default_collection.php
 * Query: from, to (YYYY-MM-DD, default last 30 days)
 * Every payment collected within the date range against a loan that is past
 * its full scheduled tenure -- recovery performance on the same loan
 * population default_loans.php lists as currently outstanding. Scoped by
 * role via loan_scope_where().
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();

$from = trim((string)($_GET['from'] ?? date('Y-m-d', strtotime('-30 days'))));
$to = trim((string)($_GET['to'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    json_response(false, null, 'from/to must be YYYY-MM-DD', 422);
}

[$scopeSql, $scopeParams] = loan_scope_where($claims, 'l');
$where = $scopeSql !== '' ? "AND $scopeSql" : '';

$stmt = $pdo->prepare("
  ;WITH Matured AS (
    SELECT rs.LoanID, MAX(rs.DueDate) AS LastDueDate
    FROM Repayment_Schedule rs
    GROUP BY rs.LoanID
    HAVING MAX(rs.DueDate) < CAST(GETDATE() AS DATE)
  )
  SELECT c.CollectionDate, l.LoanID, l.LoanNo, l.LoanCycle, m.FirstName, m.LastName, m.MobileNo,
         b.BranchName, ctr.CenterName, officer.FullName AS OfficerName, mt.LastDueDate, c.TotalCollected
  FROM Collection c
  JOIN Matured mt ON c.LoanID = mt.LoanID
  JOIN Loan l ON c.LoanID = l.LoanID
  JOIN Member m ON l.MemberID = m.MemberID
  LEFT JOIN Branch b ON l.BranchID = b.BranchID
  LEFT JOIN Center ctr ON l.CenterID = ctr.CenterID
  LEFT JOIN Staff officer ON l.LoanOfficerStaffID = officer.StaffID
  WHERE c.CollectionDate BETWEEN ? AND ? $where
  ORDER BY c.CollectionDate DESC
");
$stmt->execute(array_merge([$from, $to], $scopeParams));
$rows = $stmt->fetchAll();

json_response(true, [
    'from' => $from,
    'to' => $to,
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
            'tenure_ended_on' => to_iso_date($r['LastDueDate']),
            'collection_date' => to_iso_date($r['CollectionDate']),
            'amount_collected' => to_money($r['TotalCollected']),
        ];
    }, $rows),
]);

<?php
/**
 * GET /api/v1/reports/arrear_collection.php
 * Query: from, to (YYYY-MM-DD, default last 30 days)
 * Every payment actually recovered against a broken (overdue-at-the-time)
 * installment within the date range -- recovery performance, as opposed to
 * arrears_clients.php's current worklist. Scoped by role via loan_scope_where().
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
  SELECT c.CollectionDate, l.LoanID, l.LoanNo, l.LoanCycle, m.FirstName, m.LastName, m.MobileNo,
         b.BranchName, ctr.CenterName, officer.FullName AS OfficerName,
         rs.InstallmentNo, rs.DueDate, c.TotalCollected,
         DATEDIFF(DAY, rs.DueDate, c.CollectionDate) AS DaysLate
  FROM Collection c
  JOIN Repayment_Schedule rs ON c.ScheduleID = rs.ScheduleID
  JOIN Loan l ON c.LoanID = l.LoanID
  JOIN Member m ON l.MemberID = m.MemberID
  LEFT JOIN Branch b ON l.BranchID = b.BranchID
  LEFT JOIN Center ctr ON l.CenterID = ctr.CenterID
  LEFT JOIN Staff officer ON l.LoanOfficerStaffID = officer.StaffID
  WHERE c.CollectionDate BETWEEN ? AND ? AND c.CollectionDate > rs.DueDate $where
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
            'installment_no' => (int)$r['InstallmentNo'],
            'due_date' => to_iso_date($r['DueDate']),
            'collection_date' => to_iso_date($r['CollectionDate']),
            'days_late' => (int)$r['DaysLate'],
            'amount_collected' => to_money($r['TotalCollected']),
        ];
    }, $rows),
]);

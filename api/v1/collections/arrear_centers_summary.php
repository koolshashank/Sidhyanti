<?php
/**
 * GET /api/v1/collections/arrear_centers_summary.php
 *
 * Per-center rollup of all-time overdue installments -- feeds the "Arrear"
 * tab's center list. Deliberately a different data source than the
 * "Collection" tab: arrears are about installments that are *already*
 * unpaid past their due date regardless of today's date, which is the
 * established Repayment_Schedule-based definition this CRM already uses
 * elsewhere (dashboard.php/portfolio.php's UnpaidAgg pattern) -- NOT
 * Daily_Collection_Sheet, which only tracks a rolling window of due-today
 * rows.
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();
[$scopeSql, $scopeParams] = loan_scope_where($claims, 'l');
$where = ['ua.OldestUnpaidDue < CAST(GETDATE() AS DATE)'];
$params = [];
if ($scopeSql !== '') { $where[] = $scopeSql; array_push($params, ...$scopeParams); }
$whereSql = 'WHERE ' . implode(' AND ', $where);

$stmt = $pdo->prepare("
  ;WITH UnpaidAgg AS (
    SELECT rs.LoanID, MIN(rs.DueDate) AS OldestUnpaidDue, SUM(rs.PrincipalDue) AS OverdueAmount
    FROM Repayment_Schedule rs
    WHERE rs.IsPaid = 0
    GROUP BY rs.LoanID
  )
  SELECT l.CenterID, c.CenterName, c.MeetingDay, c.MeetingTime, b.BranchName,
         COUNT(DISTINCT ua.LoanID) AS ClientCount,
         COALESCE(SUM(ua.OverdueAmount),0) AS ArrearAmount,
         MAX(DATEDIFF(DAY, ua.OldestUnpaidDue, GETDATE())) AS MaxDaysOverdue
  FROM UnpaidAgg ua
  JOIN Loan l ON ua.LoanID = l.LoanID
  LEFT JOIN Center c ON l.CenterID = c.CenterID
  LEFT JOIN Branch b ON l.BranchID = b.BranchID
  $whereSql
  GROUP BY l.CenterID, c.CenterName, c.MeetingDay, c.MeetingTime, b.BranchName
  ORDER BY c.CenterName
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$items = array_map(function ($r) {
    return [
        'center_id'         => $r['CenterID'] !== null ? (int)$r['CenterID'] : null,
        'center_name'       => $r['CenterName'],
        'branch_name'       => $r['BranchName'],
        'meeting_day'       => $r['MeetingDay'],
        'meeting_time'      => $r['MeetingTime'],
        'client_count'      => (int)$r['ClientCount'],
        'arrear_amount'     => to_money($r['ArrearAmount']),
        'max_days_overdue'  => (int)$r['MaxDaysOverdue'],
    ];
}, $rows);

json_response(true, ['items' => $items]);

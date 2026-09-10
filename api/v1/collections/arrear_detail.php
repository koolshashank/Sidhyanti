<?php
/**
 * GET /api/v1/collections/arrear_detail.php?center_id={center_id}
 *
 * Per-borrower overdue list for one center (see arrear_centers_summary.php
 * for the data-source rationale: Repayment_Schedule-based, not
 * Daily_Collection_Sheet). Shaped like collections/due.php's items so the
 * mobile app can reuse the same borrower-row widget and "Collect EMI" /
 * record-payment flow for both tabs -- due_amount/installment_no here refer
 * to the borrower's next unpaid installment, and status is always
 * "Overdue".
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();
$centerId = (int)($_GET['center_id'] ?? 0);
if ($centerId <= 0) {
    json_response(false, null, 'center_id is required', 422);
}

[$scopeSql, $scopeParams] = loan_scope_where($claims, 'l');
$where = ['ua.OldestUnpaidDue < CAST(GETDATE() AS DATE)', 'l.CenterID = ?'];
$params = [$centerId];
if ($scopeSql !== '') { $where[] = $scopeSql; array_push($params, ...$scopeParams); }
$whereSql = 'WHERE ' . implode(' AND ', $where);

$stmt = $pdo->prepare("
  ;WITH UnpaidAgg AS (
    SELECT rs.LoanID, MIN(rs.DueDate) AS OldestUnpaidDue, SUM(rs.PrincipalDue) AS OverdueAmount, COUNT(*) AS OverdueCount
    FROM Repayment_Schedule rs
    WHERE rs.IsPaid = 0
    GROUP BY rs.LoanID
  )
  SELECT l.LoanID, l.LoanNo, l.CenterID, c.CenterName, m.FirstName, m.LastName,
         COALESCE(l.LoanAmountSanctioned, l.LoanAmountApplied) AS TotalDisbursed,
         (SELECT COALESCE(SUM(col.TotalCollected),0) FROM Collection col WHERE col.LoanID = l.LoanID) AS TotalCollected,
         ua.OverdueAmount, ua.OverdueCount, ua.OldestUnpaidDue,
         DATEDIFF(DAY, ua.OldestUnpaidDue, GETDATE()) AS DaysOverdue,
         (SELECT TOP 1 rs2.ScheduleID FROM Repayment_Schedule rs2 WHERE rs2.LoanID = l.LoanID AND rs2.IsPaid = 0 ORDER BY rs2.InstallmentNo) AS NextScheduleID,
         (SELECT TOP 1 rs2.InstallmentNo FROM Repayment_Schedule rs2 WHERE rs2.LoanID = l.LoanID AND rs2.IsPaid = 0 ORDER BY rs2.InstallmentNo) AS NextInstallmentNo,
         (SELECT TOP 1 rs2.EMIAmount FROM Repayment_Schedule rs2 WHERE rs2.LoanID = l.LoanID AND rs2.IsPaid = 0 ORDER BY rs2.InstallmentNo) AS NextEmiAmount
  FROM UnpaidAgg ua
  JOIN Loan l ON ua.LoanID = l.LoanID
  JOIN Member m ON l.MemberID = m.MemberID
  LEFT JOIN Center c ON l.CenterID = c.CenterID
  $whereSql
  ORDER BY m.FirstName
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$items = array_map(function ($r) {
    return [
        'loan_id'             => (int)$r['LoanID'],
        'loan_no'             => $r['LoanNo'],
        'schedule_id'         => $r['NextScheduleID'] !== null ? (int)$r['NextScheduleID'] : null,
        'installment_no'      => $r['NextInstallmentNo'] !== null ? (int)$r['NextInstallmentNo'] : null,
        'borrower_name'       => trim($r['FirstName'] . ' ' . $r['LastName']),
        'co_borrower_name'    => null,
        'center_id'           => $r['CenterID'] !== null ? (int)$r['CenterID'] : null,
        'center_name'         => $r['CenterName'],
        'collection_due_date' => to_iso_date($r['OldestUnpaidDue']),
        'due_amount'          => to_money($r['NextEmiAmount']),
        'collected_amount'    => 0,
        'status'              => 'Overdue',
        'total_disbursed'     => to_money($r['TotalDisbursed']),
        'total_collected'     => to_money($r['TotalCollected']),
        'arrear_amount'       => to_money($r['OverdueAmount']),
        'overdue_count'       => (int)$r['OverdueCount'],
        'days_overdue'        => (int)$r['DaysOverdue'],
    ];
}, $rows);

json_response(true, ['items' => $items]);

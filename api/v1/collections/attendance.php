<?php
/**
 * GET /api/v1/collections/attendance.php?center_id=&date=
 *
 * Lists every loan due for collection at this center on this date (the same
 * Daily_Collection_Sheet set collections/due.php would show for a single
 * day), each with its attendance status if already marked. Used to gate the
 * mobile "Today" collection screen -- attendance must be completed before
 * the collection list is shown.
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();

$centerId = (int)($_GET['center_id'] ?? 0);
$date = trim((string)($_GET['date'] ?? ''));
if ($centerId <= 0) {
    json_response(false, null, 'center_id is required', 422);
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    json_response(false, null, 'date (YYYY-MM-DD) is required', 422);
}

[$scopeSql, $scopeParams] = collection_scope_where($claims, 'dcs');
$where = ['dcs.CollectionDueDate = ?', 'dcs.CenterID = ?'];
$params = [$date, $centerId];
if ($scopeSql !== '') { $where[] = $scopeSql; array_push($params, ...$scopeParams); }
$whereSql = 'WHERE ' . implode(' AND ', $where);

$stmt = $pdo->prepare("
  SELECT dcs.LoanID, dcs.BorrowerName, dcs.CoBorrowerName, dcs.EMIAmount, a.Status
  FROM Daily_Collection_Sheet dcs
  LEFT JOIN Center_Meeting_Attendance a ON a.LoanID = dcs.LoanID AND a.CenterID = dcs.CenterID AND a.AttendanceDate = ?
  $whereSql
  ORDER BY dcs.BorrowerName
");
$stmt->execute(array_merge([$date], $params));
$rows = $stmt->fetchAll();

$markedCount = 0;
$items = array_map(function ($r) use (&$markedCount) {
    if ($r['Status'] !== null) {
        $markedCount++;
    }
    return [
        'loan_id'          => (int)$r['LoanID'],
        'borrower_name'    => $r['BorrowerName'],
        'co_borrower_name' => $r['CoBorrowerName'],
        'due_amount'       => to_money($r['EMIAmount']),
        'status'           => $r['Status'],
    ];
}, $rows);

json_response(true, [
    'items'        => $items,
    'total_due'    => count($items),
    'marked_count' => $markedCount,
    'is_complete'  => $markedCount === count($items),
]);

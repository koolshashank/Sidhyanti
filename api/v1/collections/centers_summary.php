<?php
/**
 * GET /api/v1/collections/centers_summary.php
 * Query: date_from, date_to (default both = today, Y-m-d)
 *
 * Per-center rollup of today's (or the given date range's) Daily_Collection_Sheet
 * rows -- feeds the "Collection" tab's center list (target/received/balance +
 * a % ring per center), same data source convention as collections/due.php.
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();

$today = date('Y-m-d');
$dateFrom = trim((string)($_GET['date_from'] ?? $today));
$dateTo = trim((string)($_GET['date_to'] ?? $today));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    json_response(false, null, 'date_from/date_to must be YYYY-MM-DD', 422);
}

[$scopeSql, $scopeParams] = collection_scope_where($claims, 'dcs');
$where = ['dcs.CollectionDueDate BETWEEN ? AND ?'];
$params = [$dateFrom, $dateTo];
if ($scopeSql !== '') { $where[] = $scopeSql; array_push($params, ...$scopeParams); }
$whereSql = 'WHERE ' . implode(' AND ', $where);

$stmt = $pdo->prepare("
  SELECT dcs.CenterID, c.CenterName, c.MeetingDay, c.MeetingTime, b.BranchName,
         COUNT(*) AS ClientCount,
         COALESCE(SUM(dcs.EMIAmount),0) AS Target,
         COALESCE(SUM(dcs.CollectionAmount),0) AS Received
  FROM Daily_Collection_Sheet dcs
  LEFT JOIN Center c ON dcs.CenterID = c.CenterID
  LEFT JOIN Branch b ON dcs.BranchID = b.BranchID
  $whereSql
  GROUP BY dcs.CenterID, c.CenterName, c.MeetingDay, c.MeetingTime, b.BranchName
  ORDER BY c.CenterName
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$items = array_map(function ($r) {
    $target = to_money($r['Target']);
    $received = to_money($r['Received']);
    return [
        'center_id'    => $r['CenterID'] !== null ? (int)$r['CenterID'] : null,
        'center_name'  => $r['CenterName'],
        'branch_name'  => $r['BranchName'],
        'meeting_day'  => $r['MeetingDay'],
        'meeting_time' => $r['MeetingTime'],
        'client_count' => (int)$r['ClientCount'],
        'target'       => $target,
        'received'     => $received,
        'balance'      => $target - $received,
        'percent'      => $target > 0 ? round(($received / $target) * 100, 2) : 0,
    ];
}, $rows);

json_response(true, ['items' => $items]);

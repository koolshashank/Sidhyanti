<?php
/**
 * GET /api/v1/reports/center_collection.php
 * Query: from, to (YYYY-MM-DD, default last 30 days)
 * Scoped like the web report: Field Officer -> own book, Branch Manager ->
 * own branch, Area Manager -> every mapped branch, Admin -> everything.
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

[$scopeSql, $scopeParams] = collection_scope_where($claims, 'dcs');
$where = $scopeSql !== '' ? "AND $scopeSql" : '';

$stmt = $pdo->prepare("
  SELECT c.CenterName, b.BranchName, COUNT(DISTINCT dcs.LoanID) AS ClientCount,
    SUM(dcs.EMIAmount) AS DueAmount, COALESCE(SUM(dcs.CollectionAmount),0) AS CollectedAmount
  FROM Daily_Collection_Sheet dcs
  JOIN Center c ON dcs.CenterID = c.CenterID
  LEFT JOIN Branch b ON dcs.BranchID = b.BranchID
  WHERE dcs.CollectionDueDate BETWEEN ? AND ? $where
  GROUP BY c.CenterName, b.BranchName
  ORDER BY b.BranchName, c.CenterName
");
$stmt->execute(array_merge([$from, $to], $scopeParams));
$rows = $stmt->fetchAll();

json_response(true, [
    'from' => $from,
    'to' => $to,
    'items' => array_map(function ($r) {
        $due = to_money($r['DueAmount']);
        $collected = to_money($r['CollectedAmount']);
        return [
            'center_name' => $r['CenterName'],
            'branch_name' => $r['BranchName'],
            'client_count' => (int)$r['ClientCount'],
            'due_amount' => $due,
            'collected_amount' => $collected,
            'balance' => round($due - $collected, 2),
        ];
    }, $rows),
]);

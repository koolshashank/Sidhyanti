<?php
/**
 * GET /api/v1/reports/dcr.php
 * Query: date (YYYY-MM-DD, default today), center_id (optional)
 *
 * Daily Collection Report -- per project convention, reads from
 * Daily_Collection_Sheet (never Repayment_Schedule/Collection directly for
 * reporting). Branch-scoped via the existing collection_scope_where().
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();

$date = trim((string)($_GET['date'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    json_response(false, null, 'date must be YYYY-MM-DD', 422);
}
$centerId = (int)($_GET['center_id'] ?? 0);

[$scopeSql, $scopeParams] = collection_scope_where($claims, 'dcs');
$where = ['dcs.CollectionDueDate = ?'];
$params = [$date];
if ($scopeSql !== '') { $where[] = $scopeSql; array_push($params, ...$scopeParams); }
if ($centerId > 0) { $where[] = 'dcs.CenterID = ?'; $params[] = $centerId; }
$whereSql = 'WHERE ' . implode(' AND ', $where);

$summaryStmt = $pdo->prepare("
  SELECT COUNT(*) AS DueCount,
         COALESCE(SUM(dcs.EMIAmount), 0) AS DueAmount,
         COALESCE(SUM(CASE WHEN dcs.CollectionStatus = 'Collected' THEN dcs.CollectionAmount ELSE 0 END), 0) AS CollectedAmount,
         SUM(CASE WHEN dcs.CollectionStatus = 'Collected' THEN 1 ELSE 0 END) AS CollectedCount,
         SUM(CASE WHEN dcs.CollectionStatus IS NULL THEN 1 ELSE 0 END) AS PendingCount,
         SUM(CASE WHEN dcs.IsPartPayment = 1 THEN 1 ELSE 0 END) AS PartialCount
  FROM Daily_Collection_Sheet dcs
  $whereSql
");
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch();

$byCenterStmt = $pdo->prepare("
  SELECT dcs.CenterID, c.CenterName,
         COUNT(*) AS DueCount,
         COALESCE(SUM(dcs.EMIAmount), 0) AS DueAmount,
         COALESCE(SUM(CASE WHEN dcs.CollectionStatus = 'Collected' THEN dcs.CollectionAmount ELSE 0 END), 0) AS CollectedAmount,
         SUM(CASE WHEN dcs.CollectionStatus = 'Collected' THEN 1 ELSE 0 END) AS CollectedCount,
         SUM(CASE WHEN dcs.CollectionStatus IS NULL THEN 1 ELSE 0 END) AS PendingCount,
         SUM(CASE WHEN dcs.IsPartPayment = 1 THEN 1 ELSE 0 END) AS PartialCount
  FROM Daily_Collection_Sheet dcs
  LEFT JOIN Center c ON dcs.CenterID = c.CenterID
  $whereSql
  GROUP BY dcs.CenterID, c.CenterName
  ORDER BY c.CenterName
");
$byCenterStmt->execute($params);
$byCenter = $byCenterStmt->fetchAll();

json_response(true, [
    'date' => $date,
    'summary' => [
        'due_count'        => (int)$summary['DueCount'],
        'due_amount'       => to_money($summary['DueAmount']),
        'collected_amount' => to_money($summary['CollectedAmount']),
        'collected_count'  => (int)$summary['CollectedCount'],
        'pending_count'    => (int)$summary['PendingCount'],
        'partial_count'    => (int)$summary['PartialCount'],
    ],
    'by_center' => array_map(function ($r) {
        return [
            'center_id'        => $r['CenterID'] !== null ? (int)$r['CenterID'] : null,
            'center_name'      => $r['CenterName'] ?? 'Unassigned',
            'due_count'        => (int)$r['DueCount'],
            'due_amount'       => to_money($r['DueAmount']),
            'collected_amount' => to_money($r['CollectedAmount']),
            'collected_count'  => (int)$r['CollectedCount'],
            'pending_count'    => (int)$r['PendingCount'],
            'partial_count'    => (int)$r['PartialCount'],
        ];
    }, $byCenter),
]);

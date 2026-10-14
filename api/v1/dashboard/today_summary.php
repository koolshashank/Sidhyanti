<?php
/**
 * GET /api/v1/dashboard/today_summary.php
 * Quick at-a-glance numbers for the mobile dashboard header: today's
 * collection (due vs. already collected) scoped by role the same way the
 * Today's Pending Collection report is (see reports/today_pending_collection.php).
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();

[$scopeSql, $scopeParams] = collection_scope_where($claims, 'dcs');
$where = $scopeSql !== '' ? "AND $scopeSql" : '';

$stmt = $pdo->prepare("
  SELECT
    COUNT(*) AS DueCount,
    SUM(dcs.EMIAmount) AS DueAmount,
    SUM(CASE WHEN dcs.CollectionStatus IN ('Collected','Preclosed') THEN dcs.EMIAmount ELSE 0 END) AS CollectedAmount,
    SUM(CASE WHEN dcs.CollectionStatus IN ('Collected','Preclosed') THEN 1 ELSE 0 END) AS CollectedCount
  FROM Daily_Collection_Sheet dcs
  WHERE dcs.CollectionDueDate = CAST(GETDATE() AS DATE) $where
");
$stmt->execute($scopeParams);
$r = $stmt->fetch();

json_response(true, [
    'due_amount'       => to_money($r['DueAmount'] ?? 0),
    'collected_amount' => to_money($r['CollectedAmount'] ?? 0),
    'due_count'        => (int)($r['DueCount'] ?? 0),
    'collected_count'  => (int)($r['CollectedCount'] ?? 0),
]);

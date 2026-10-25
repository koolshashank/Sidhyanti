<?php
/**
 * GET /api/v1/reports/cash_ledger.php
 * Query: date_from, date_to (default: last 30 days)
 * A branch's daily Day_Closing_Report rows as a running cash ledger --
 * GrandTotal is each day's closing balance (OpeningBalance already carries
 * forward from the prior day's GrandTotal at DCR submit time, see
 * database/34_day_closing_report.sql). Scoped to the caller's own branch
 * (Admin sees their own branch's ledger too -- there's no single "head
 * office" cash position to roll up across branches).
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();
$branchId = (int)($claims['BranchID'] ?? 0);

$dateFrom = trim((string)($_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'))));
$dateTo = trim((string)($_GET['date_to'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    json_response(false, null, 'date_from/date_to must be YYYY-MM-DD', 422);
}

$stmt = $pdo->prepare("
  SELECT ClosingDate, OpeningBalance, TotalInflow, TotalOutflow, GrandTotal
  FROM Day_Closing_Report
  WHERE BranchID = ? AND ClosingDate BETWEEN ? AND ?
  ORDER BY ClosingDate
");
$stmt->execute([$branchId, $dateFrom, $dateTo]);
$rows = $stmt->fetchAll();

json_response(true, [
    'items' => array_map(function ($r) {
        return [
            'closing_date'    => to_iso_date($r['ClosingDate']),
            'opening_balance' => to_money($r['OpeningBalance']),
            'total_inflow'    => to_money($r['TotalInflow']),
            'total_outflow'   => to_money($r['TotalOutflow']),
            'grand_total'     => to_money($r['GrandTotal']),
        ];
    }, $rows),
]);

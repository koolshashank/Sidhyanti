<?php
/**
 * GET /api/v1/collections/due
 * Query: date_from, date_to (default both = today, Y-m-d), center_id (optional),
 *        page, per_page (max 200)
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
$centerId = (int)($_GET['center_id'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(200, max(1, (int)($_GET['per_page'] ?? 50)));

[$scopeSql, $scopeParams] = collection_scope_where($claims, 'dcs');

$where = ['dcs.CollectionDueDate BETWEEN ? AND ?'];
$params = [$dateFrom, $dateTo];
if ($scopeSql !== '') { $where[] = $scopeSql; array_push($params, ...$scopeParams); }
if ($centerId > 0) { $where[] = 'dcs.CenterID = ?'; $params[] = $centerId; }
$whereSql = 'WHERE ' . implode(' AND ', $where);

$summaryStmt = $pdo->prepare("
  SELECT COUNT(*) AS ClientCount,
         COALESCE(SUM(dcs.EMIAmount),0) AS DueAmount,
         COALESCE(SUM(dcs.CollectionAmount),0) AS CollectedAmount
  FROM Daily_Collection_Sheet dcs
  $whereSql
");
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch();

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM Daily_Collection_Sheet dcs $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sql = "
  SELECT dcs.LoanID, l.LoanNo, dcs.ScheduleID, dcs.InstallmentNo, dcs.BorrowerName, dcs.CoBorrowerName,
         dcs.CenterID, c.CenterName, dcs.CollectionDueDate,
         dcs.EMIAmount AS DueAmount, dcs.CollectionAmount, dcs.CollectionStatus,
         COALESCE(l.LoanAmountSanctioned, l.LoanAmountApplied) AS TotalDisbursed,
         (SELECT COALESCE(SUM(col.TotalCollected),0) FROM Collection col WHERE col.LoanID = dcs.LoanID) AS TotalCollected,
         m.MemberID, m.MobileNo, m.VisitOrder
  FROM Daily_Collection_Sheet dcs
  JOIN Loan l ON dcs.LoanID = l.LoanID
  JOIN Member m ON l.MemberID = m.MemberID
  LEFT JOIN Center c ON dcs.CenterID = c.CenterID
  $whereSql
  ORDER BY c.CenterName, COALESCE(m.VisitOrder, 999999), dcs.BorrowerName
  OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
";
$stmt = $pdo->prepare($sql);
$i = 1;
foreach ($params as $p) { $stmt->bindValue($i++, $p); }
$stmt->bindValue($i++, $offset, PDO::PARAM_INT);
$stmt->bindValue($i++, $perPage, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$items = array_map(function ($r) {
    return [
        'loan_id'             => (int)$r['LoanID'],
        'loan_no'             => $r['LoanNo'],
        'member_id'           => (int)$r['MemberID'],
        'schedule_id'         => (int)$r['ScheduleID'],
        'installment_no'      => (int)$r['InstallmentNo'],
        'borrower_name'       => $r['BorrowerName'],
        'co_borrower_name'    => $r['CoBorrowerName'],
        'mobile_no'           => $r['MobileNo'],
        'center_id'           => $r['CenterID'] !== null ? (int)$r['CenterID'] : null,
        'center_name'         => $r['CenterName'],
        'visit_order'         => $r['VisitOrder'] !== null ? (int)$r['VisitOrder'] : null,
        'collection_due_date' => to_iso_date($r['CollectionDueDate']),
        'due_amount'          => to_money($r['DueAmount']),
        'collected_amount'    => to_money($r['CollectionAmount']),
        'status'              => $r['CollectionStatus'] ?: 'Pending',
        'total_disbursed'     => to_money($r['TotalDisbursed']),
        'total_collected'     => to_money($r['TotalCollected']),
    ];
}, $rows);

json_response(true, [
    'summary' => [
        'client_count'     => (int)$summary['ClientCount'],
        'due_amount'       => to_money($summary['DueAmount']),
        'collected_amount' => to_money($summary['CollectedAmount']),
        'balance'          => to_money($summary['DueAmount']) - to_money($summary['CollectedAmount']),
    ],
    'items'       => $items,
    'page'        => $page,
    'per_page'    => $perPage,
    'total'       => $total,
    'total_pages' => $totalPages,
]);

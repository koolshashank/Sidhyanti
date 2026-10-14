<?php
/**
 * GET /api/v1/reports/today_pending_collection.php
 * Today's not-yet-collected installments, scoped by role (see
 * center_collection.php for the scoping rationale).
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
  SELECT dcs.LoanID, l.LoanNo, dcs.BorrowerName, dcs.CoBorrowerName, b.BranchName, c.CenterName,
         officer.FullName AS OfficerName, dcs.EMIAmount
  FROM Daily_Collection_Sheet dcs
  JOIN Loan l ON dcs.LoanID = l.LoanID
  LEFT JOIN Branch b ON dcs.BranchID = b.BranchID
  LEFT JOIN Center c ON dcs.CenterID = c.CenterID
  LEFT JOIN Staff officer ON dcs.OfficerStaffID = officer.StaffID
  WHERE dcs.CollectionDueDate = CAST(GETDATE() AS DATE) AND dcs.CollectionStatus = 'Pending' $where
  ORDER BY b.BranchName, c.CenterName, dcs.BorrowerName
");
$stmt->execute($scopeParams);
$rows = $stmt->fetchAll();

json_response(true, [
    'items' => array_map(function ($r) {
        return [
            'loan_id' => (int)$r['LoanID'],
            'loan_no' => $r['LoanNo'],
            'borrower_name' => $r['BorrowerName'],
            'co_borrower_name' => $r['CoBorrowerName'],
            'branch_name' => $r['BranchName'],
            'center_name' => $r['CenterName'],
            'officer_name' => $r['OfficerName'],
            'emi_amount' => to_money($r['EMIAmount']),
        ];
    }, $rows),
]);

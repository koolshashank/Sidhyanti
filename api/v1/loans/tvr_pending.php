<?php
/**
 * GET /api/v1/loans/tvr_pending.php
 *
 * Branch-scoped worklist of loans that still need a Tele-Verification call
 * (TVR_Details) -- pre-disbursement loans (Applied/GRT Pending/Approved,
 * same stages tvr.php's own header describes as "pre-disbursement calls")
 * that don't have a TVR_Details row yet.
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';
require_once __DIR__ . '/../../../includes/permission_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();

if (!role_has_permission($pdo, $claims['RoleID'] ?? null, 'conduct_tvr')) {
    json_response(false, null, 'You do not have permission to conduct TVR', 403);
}

[$whereScope, $scopeParams] = loan_scope_where($claims, 'l');
$where = $whereScope !== '' ? "AND $whereScope" : '';

$stmt = $pdo->prepare("
  SELECT l.LoanID, l.LoanNo, l.LoanAmountSanctioned, l.LoanAmountApplied, l.ApplicationDate,
         m.FirstName, m.LastName, c.CenterName, stg.StageName
  FROM Loan l
  JOIN Member m ON l.MemberID = m.MemberID
  LEFT JOIN Center c ON l.CenterID = c.CenterID
  LEFT JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
  WHERE stg.StageName IN ('Applied', 'GRT Pending', 'Approved')
    AND NOT EXISTS (SELECT 1 FROM TVR_Details tv WHERE tv.LoanID = l.LoanID)
    $where
  ORDER BY l.ApplicationDate ASC
");
$stmt->execute($scopeParams);
$rows = $stmt->fetchAll();

$statuses = $pdo->query("SELECT TVRStatusID, TVRStatusName FROM TVR_Status_Master ORDER BY TVRStatusID")->fetchAll();

json_response(true, [
    'items' => array_map(function ($r) {
        return [
            'loan_id'          => (int)$r['LoanID'],
            'loan_no'          => $r['LoanNo'],
            'member_name'      => trim($r['FirstName'] . ' ' . $r['LastName']),
            'center_name'      => $r['CenterName'],
            'loan_amount'      => to_money($r['LoanAmountSanctioned'] ?: $r['LoanAmountApplied']),
            'stage'            => $r['StageName'],
            'application_date' => to_iso_date($r['ApplicationDate']),
        ];
    }, $rows),
    'tvr_statuses' => array_map(fn($s) => ['id' => (int)$s['TVRStatusID'], 'name' => $s['TVRStatusName']], $statuses),
]);

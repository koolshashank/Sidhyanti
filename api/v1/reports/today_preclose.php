<?php
/**
 * GET /api/v1/reports/today_preclose.php
 * Query: from, to (YYYY-MM-DD, default today for both -- "today's
 * preclosures" unless a date range is explicitly given)
 *
 * Every loan preclose approved (i.e. actually processed, not just
 * requested) within the date range, and how much it precosed for. Sourced
 * from Loan_Approval (RequestType='Preclose', Status='Approved') --
 * ReviewedAt is the preclose date and PrecloseQuoteAmount (captured at
 * request time, see loan_preclose_quote()) is the payoff amount; nothing
 * else in the schema records this. Scoped by role via loan_scope_where().
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();

$from = trim((string)($_GET['from'] ?? date('Y-m-d')));
$to = trim((string)($_GET['to'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    json_response(false, null, 'from/to must be YYYY-MM-DD', 422);
}

[$scopeSql, $scopeParams] = loan_scope_where($claims, 'l');
$where = $scopeSql !== '' ? "AND $scopeSql" : '';

$stmt = $pdo->prepare("
  SELECT la.LoanApprovalID, la.ReviewedAt, la.PrecloseQuoteAmount,
         l.LoanID, l.LoanNo, l.LoanCycle, m.FirstName, m.LastName, m.MobileNo,
         b.BranchName, ctr.CenterName, officer.FullName AS OfficerName,
         reviewer.FullName AS ApprovedByName
  FROM Loan_Approval la
  JOIN Loan l ON la.LoanID = l.LoanID
  JOIN Member m ON l.MemberID = m.MemberID
  LEFT JOIN Branch b ON l.BranchID = b.BranchID
  LEFT JOIN Center ctr ON l.CenterID = ctr.CenterID
  LEFT JOIN Staff officer ON l.LoanOfficerStaffID = officer.StaffID
  LEFT JOIN Staff reviewer ON la.ReviewedByStaffID = reviewer.StaffID
  WHERE la.RequestType = 'Preclose' AND la.Status = 'Approved'
    AND CAST(la.ReviewedAt AS DATE) BETWEEN ? AND ? $where
  ORDER BY la.ReviewedAt DESC
");
$stmt->execute(array_merge([$from, $to], $scopeParams));
$rows = $stmt->fetchAll();

json_response(true, [
    'from' => $from,
    'to' => $to,
    'items' => array_map(function ($r) {
        return [
            'loan_id'         => (int)$r['LoanID'],
            'loan_no'         => $r['LoanNo'],
            'loan_cycle'      => $r['LoanCycle'] !== null ? (int)$r['LoanCycle'] : null,
            'member_name'     => trim($r['FirstName'] . ' ' . $r['LastName']),
            'mobile_no'       => $r['MobileNo'],
            'branch_name'     => $r['BranchName'],
            'center_name'     => $r['CenterName'],
            'officer_name'    => $r['OfficerName'],
            'approved_by'     => $r['ApprovedByName'],
            'preclose_date'   => to_iso_date($r['ReviewedAt']),
            'preclose_amount' => to_money($r['PrecloseQuoteAmount']),
        ];
    }, $rows),
]);

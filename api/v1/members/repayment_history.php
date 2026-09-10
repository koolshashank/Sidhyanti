<?php
/**
 * GET /api/v1/members/repayment_history?member_id={member_id}
 *
 * Aggregates the repayment schedule across every loan this member has ever
 * had (not just the latest one), same dual-source Paid logic as
 * loans/detail.php (Collection ledger OR Daily_Collection_Sheet, whichever
 * has a collection date first).
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();
$memberId = (int)($_GET['member_id'] ?? 0);
if ($memberId <= 0) {
    json_response(false, null, 'member_id is required', 422);
}

$memberStmt = $pdo->prepare("SELECT AssignedToStaffID, BranchID FROM Member WHERE MemberID = ?");
$memberStmt->execute([$memberId]);
$member = $memberStmt->fetch();
if (!$member) {
    json_response(false, null, 'Member not found', 404);
}
assert_member_access($member, $claims);

$stmt = $pdo->prepare("
  SELECT l.LoanID, l.LoanNo, rs.InstallmentNo, rs.DueDate, rs.EMIAmount, rs.PrincipalDue, rs.InterestDue, rs.IsPaid,
    (SELECT MAX(c.CollectionDate) FROM Collection c WHERE c.ScheduleID = rs.ScheduleID) AS LedgerCollectionDate,
    (SELECT TOP 1 dcs.CollectionDate FROM Daily_Collection_Sheet dcs WHERE dcs.ScheduleID = rs.ScheduleID AND dcs.CollectionStatus IN ('Collected','Preclosed')) AS SheetCollectionDate
  FROM Repayment_Schedule rs
  JOIN Loan l ON rs.LoanID = l.LoanID
  WHERE l.MemberID = ?
  ORDER BY l.LoanID DESC, rs.InstallmentNo ASC
");
$stmt->execute([$memberId]);
$rows = $stmt->fetchAll();

$today = date('Y-m-d');
$items = array_map(function ($r) use ($today) {
    $collectionDate = $r['LedgerCollectionDate'] ?: $r['SheetCollectionDate'];
    $isPaid = to_bool($r['IsPaid']) || $collectionDate;
    $dueDateIso = to_iso_date($r['DueDate']);
    $status = 'Pending';
    if ($isPaid) {
        $status = 'Paid';
    } elseif ($dueDateIso && $dueDateIso < $today) {
        $status = 'Overdue';
    }
    return [
        'loan_id'         => (int)$r['LoanID'],
        'loan_no'         => $r['LoanNo'],
        'installment_no'  => (int)$r['InstallmentNo'],
        'due_date'        => $dueDateIso,
        'emi_amount'      => to_money($r['EMIAmount']),
        'principal_due'   => to_money($r['PrincipalDue']),
        'interest_due'    => to_money($r['InterestDue']),
        'is_paid'         => $isPaid,
        'collection_date' => to_iso_date($collectionDate),
        'status'          => $status,
    ];
}, $rows);

json_response(true, ['items' => $items]);

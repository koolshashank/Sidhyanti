<?php
/**
 * GET /api/v1/loans/detail?id={loan_id}
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';
require_once __DIR__ . '/../../../includes/permission_helper.php';
require_once __DIR__ . '/../../../includes/loan_preclose_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    json_response(false, null, 'id is required', 422);
}

$stmt = $pdo->prepare("
  SELECT l.*, m.FirstName, m.LastName, m.MobileNo, m.EnrollmentNo,
         b.BranchName, c.CenterName, p.PurposeName, stg.StageName, officer.FullName AS OfficerName
  FROM Loan l
  JOIN Member m ON l.MemberID = m.MemberID
  LEFT JOIN Branch b ON l.BranchID = b.BranchID
  LEFT JOIN Center c ON l.CenterID = c.CenterID
  LEFT JOIN Loan_Purpose_Master p ON l.PurposeID = p.PurposeID
  LEFT JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
  LEFT JOIN Staff officer ON l.LoanOfficerStaffID = officer.StaffID
  WHERE l.LoanID = ?
");
$stmt->execute([$id]);
$l = $stmt->fetch();
if (!$l) {
    json_response(false, null, 'Loan not found', 404);
}
assert_loan_access($l, $claims);

$scheduleStmt = $pdo->prepare("
  SELECT rs.*,
    (SELECT MAX(c.CollectionDate) FROM Collection c WHERE c.ScheduleID = rs.ScheduleID) AS LedgerCollectionDate,
    (SELECT TOP 1 dcs.CollectionDate FROM Daily_Collection_Sheet dcs WHERE dcs.ScheduleID = rs.ScheduleID AND dcs.CollectionStatus IN ('Collected','Preclosed')) AS SheetCollectionDate
  FROM Repayment_Schedule rs WHERE rs.LoanID=? ORDER BY rs.InstallmentNo
");
$scheduleStmt->execute([$id]);
$schedule = $scheduleStmt->fetchAll();

$today = date('Y-m-d');
$paidCount = 0;
$overdueItems = [];
$overdueAmount = 0.0;
$maxDaysOverdue = 0;

$scheduleOut = array_map(function ($r) use (&$paidCount, &$overdueItems, &$overdueAmount, &$maxDaysOverdue, $today) {
    $collectionDate = $r['LedgerCollectionDate'] ?: $r['SheetCollectionDate'];
    $isPaid = to_bool($r['IsPaid']) || $collectionDate;
    $dueDateIso = to_iso_date($r['DueDate']);
    $status = 'Pending';
    if ($isPaid) {
        $paidCount++;
        $status = 'Paid';
    } elseif ($dueDateIso && $dueDateIso < $today) {
        $status = 'Overdue';
        $daysOverdue = (int)((strtotime($today) - strtotime($dueDateIso)) / 86400);
        $overdueAmount += to_money($r['EMIAmount']);
        $maxDaysOverdue = max($maxDaysOverdue, $daysOverdue);
        $overdueItems[] = [
            'schedule_id'    => (int)$r['ScheduleID'],
            'installment_no' => (int)$r['InstallmentNo'],
            'due_date'       => $dueDateIso,
            'emi_amount'     => to_money($r['EMIAmount']),
            'days_overdue'   => $daysOverdue,
        ];
    }
    return [
        'schedule_id'     => (int)$r['ScheduleID'],
        'installment_no'  => (int)$r['InstallmentNo'],
        'due_date'        => $dueDateIso,
        'emi_amount'      => to_money($r['EMIAmount']),
        'principal_due'   => to_money($r['PrincipalDue']),
        'interest_due'    => to_money($r['InterestDue']),
        'is_paid'         => $isPaid,
        'collection_date' => to_iso_date($collectionDate),
        'status'          => $status,
    ];
}, $schedule);

// Once a loan reaches Approved (awaiting disbursal) its sanctioned terms
// are final -- Change Scheme is only for the Applied/GRT Pending stages,
// before approval locks the amount/tenure/interest in.
$canChangeScheme = role_has_permission($pdo, $claims['RoleID'] ?? null, 'change_loan_scheme')
    && $l['DisbursementDate'] === null
    && count($schedule) === 0
    && in_array($l['StageName'], ['Applied', 'GRT Pending'], true);

$canConductGrt = role_has_permission($pdo, $claims['RoleID'] ?? null, 'conduct_grt')
    && $l['StageName'] === 'GRT Pending';

$canRequestPreclose = false;
$precloseQuote = null;
if (role_has_permission($pdo, $claims['RoleID'] ?? null, 'request_loan_preclose')
    && in_array($l['StageName'], ['Disbursed', 'Active'], true)) {
    $precloseQuote = loan_preclose_quote($pdo, $id);

    $pendingPreclose = $pdo->prepare("SELECT 1 FROM Loan_Approval WHERE LoanID = ? AND RequestType = 'Preclose' AND Status = 'Pending'");
    $pendingPreclose->execute([$id]);

    // Same gating as preclose_request.php's own checks -- nothing owing, or
    // it's not the next installment's exact due date yet/anymore -- so the
    // button doesn't appear only to error when tapped.
    $nextDueStmt = $pdo->prepare("
      SELECT TOP 1 rs.DueDate,
        (SELECT MAX(c.CollectionDate) FROM Collection c WHERE c.ScheduleID = rs.ScheduleID) AS LedgerCollectionDate,
        (SELECT TOP 1 dcs.CollectionDate FROM Daily_Collection_Sheet dcs WHERE dcs.ScheduleID = rs.ScheduleID AND dcs.CollectionStatus IN ('Collected','Preclosed')) AS SheetCollectionDate
      FROM Repayment_Schedule rs
      WHERE rs.LoanID = ? AND rs.IsPaid = 0
      ORDER BY rs.InstallmentNo
    ");
    $nextDueStmt->execute([$id]);
    $nextInstallment = $nextDueStmt->fetch();
    $isDueToday = $nextInstallment
        && !$nextInstallment['LedgerCollectionDate'] && !$nextInstallment['SheetCollectionDate']
        && date('Y-m-d', strtotime((string)$nextInstallment['DueDate'])) === date('Y-m-d');

    $hasOutstanding = $precloseQuote !== null && $precloseQuote['principal_outstanding'] > 0;

    $canRequestPreclose = !$pendingPreclose->fetchColumn() && $isDueToday && $hasOutstanding;
}

// NPA write-off -- same 90+ days overdue threshold as
// api/v1/reports/npa_loans.php / portfolio.php's "PAR 90+" bucket.
$daysOverdue = 0;
$canRequestWriteOff = false;
if ($l['StageName'] === 'Active') {
    $overdueStmt = $pdo->prepare("SELECT MIN(DueDate) FROM Repayment_Schedule WHERE LoanID = ? AND IsPaid = 0");
    $overdueStmt->execute([$id]);
    $oldestUnpaidDue = $overdueStmt->fetchColumn();
    if ($oldestUnpaidDue) {
        $daysOverdue = (int)((strtotime(date('Y-m-d')) - strtotime($oldestUnpaidDue)) / 86400);
    }
    $pendingWriteOff = $pdo->prepare("SELECT 1 FROM Loan_WriteOff_Request WHERE LoanID = ? AND Status = 'Pending'");
    $pendingWriteOff->execute([$id]);
    $canRequestWriteOff = $daysOverdue > 90 && !$pendingWriteOff->fetchColumn();
}

json_response(true, [
    'loan_id'      => (int)$l['LoanID'],
    'loan_no'      => $l['LoanNo'],
    'loan_cycle'   => $l['LoanCycle'] !== null ? (int)$l['LoanCycle'] : null,
    'scheme_id'    => $l['SchemeID'] !== null ? (int)$l['SchemeID'] : null,
    'can_change_scheme' => $canChangeScheme,
    'can_conduct_grt' => $canConductGrt,
    'can_request_preclose' => $canRequestPreclose,
    'can_view_kfs' => $l['ApprovalDate'] !== null,
    'can_request_writeoff' => $canRequestWriteOff,
    'days_overdue' => $daysOverdue,
    'preclose_quote' => $canRequestPreclose ? $precloseQuote : null,
    'member' => [
        'member_id'     => (int)$l['MemberID'],
        'full_name'     => trim($l['FirstName'] . ' ' . $l['LastName']),
        'mobile_no'     => $l['MobileNo'],
        'enrollment_no' => $l['EnrollmentNo'],
    ],
    'branch_name'          => $l['BranchName'],
    'center_name'          => $l['CenterName'],
    'purpose_name'         => $l['PurposeName'],
    'stage'                => $l['StageName'],
    'officer_name'         => $l['OfficerName'],
    'loan_amount'          => to_money($l['LoanAmountSanctioned'] ?: $l['LoanAmountApplied']),
    'tenure_months'        => (int)$l['TenureMonths'],
    'interest_rate_percent' => to_money($l['InterestRatePercent']),
    'emi_amount'           => to_money($l['EMIAmount']),
    'application_date'     => to_iso_date($l['ApplicationDate']),
    'disbursement_date'    => to_iso_date($l['DisbursementDate']),
    'paid_count'           => $paidCount,
    'total_installments'   => count($schedule),
    'repayment_schedule'   => $scheduleOut,
    'overdue' => [
        'count'           => count($overdueItems),
        'amount'          => $overdueAmount,
        'max_days_overdue' => $maxDaysOverdue,
        'items'           => $overdueItems,
    ],
]);

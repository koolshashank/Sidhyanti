<?php
/**
 * POST /api/v1/loans/preclose_request.php
 * Body: { loan_id, remarks? }
 * Gated by "request_loan_preclose". Opens a Loan_Approval(RequestType=
 * 'Preclose', Status='Pending') row for the Branch Manager to review --
 * see api/v1/loans/approval_review.php for the approve/reject side.
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../../includes/permission_helper.php';
require_once __DIR__ . '/../../../includes/loan_preclose_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();

if (!role_has_permission($pdo, $claims['RoleID'] ?? null, 'request_loan_preclose')) {
    json_response(false, null, 'You do not have permission to request a loan preclose', 403);
}

$body = get_json_body();
$loanId = (int)($body['loan_id'] ?? 0);
$remarks = trim((string)($body['remarks'] ?? '')) ?: null;

if ($loanId <= 0) {
    json_response(false, null, 'loan_id is required', 422);
}

$loanStmt = $pdo->prepare("
  SELECT l.LoanID, l.LoanNo, l.BranchID, l.LoanOfficerStaffID, stg.StageName
  FROM Loan l LEFT JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
  WHERE l.LoanID = ?
");
$loanStmt->execute([$loanId]);
$loan = $loanStmt->fetch();
if (!$loan) {
    json_response(false, null, 'Loan not found', 404);
}
assert_loan_access($loan, $claims);

if (!in_array($loan['StageName'], ['Disbursed', 'Active'], true)) {
    json_response(false, null, 'Only a Disbursed or Active loan can be requested for preclose', 409);
}

// A loan with nothing left owing (fully paid off but not yet manually moved
// to Closed) must never be allowed into the preclose queue.
$quote = loan_preclose_quote($pdo, $loanId);
if ($quote === null || $quote['principal_outstanding'] <= 0) {
    json_response(false, null, 'This loan has no outstanding balance -- nothing to preclose', 409);
}

// Preclose can only be requested on the exact due date of the next unpaid
// installment -- not before (nothing's broken yet) and not after (once it's
// overdue, it's an arrear-collection matter, not a preclose).
$nextDueStmt = $pdo->prepare("
  SELECT TOP 1 rs.DueDate,
    (SELECT MAX(c.CollectionDate) FROM Collection c WHERE c.ScheduleID = rs.ScheduleID) AS LedgerCollectionDate,
    (SELECT TOP 1 dcs.CollectionDate FROM Daily_Collection_Sheet dcs WHERE dcs.ScheduleID = rs.ScheduleID AND dcs.CollectionStatus IN ('Collected','Preclosed')) AS SheetCollectionDate
  FROM Repayment_Schedule rs
  WHERE rs.LoanID = ? AND rs.IsPaid = 0
  ORDER BY rs.InstallmentNo
");
$nextDueStmt->execute([$loanId]);
$nextInstallment = $nextDueStmt->fetch();
if (!$nextInstallment || $nextInstallment['LedgerCollectionDate'] || $nextInstallment['SheetCollectionDate']) {
    json_response(false, null, 'This loan has no pending installments to preclose', 409);
}
$nextDueDate = date('Y-m-d', strtotime((string)$nextInstallment['DueDate']));
$today = date('Y-m-d');
if ($nextDueDate !== $today) {
    $friendlyDate = date('d-M-Y', strtotime((string)$nextInstallment['DueDate']));
    json_response(false, null, "Preclose can only be requested on the next installment's due date ($friendlyDate)", 409);
}

$existing = $pdo->prepare("SELECT 1 FROM Loan_Approval WHERE LoanID = ? AND RequestType = 'Preclose' AND Status = 'Pending'");
$existing->execute([$loanId]);
if ($existing->fetchColumn()) {
    json_response(false, null, 'A preclose request is already pending for this loan', 409);
}

$newId = insert_and_get_id($pdo, "INSERT INTO Loan_Approval
    (LoanID, RequestType, Status, RequestedByStaffID, RequestRemarks)
    VALUES (?, 'Preclose', 'Pending', ?, ?)", [
    $loanId,
    (int)$claims['StaffID'],
    $remarks,
], 'LoanApprovalID');

$bmStmt = $pdo->prepare("
  SELECT s.StaffID FROM Staff s JOIN Role_Master r ON s.RoleID = r.RoleID
  WHERE r.RoleName = 'Branch Manager' AND s.BranchID = ? AND s.IsActive = 1
");
$bmStmt->execute([$loan['BranchID']]);
$notifyIns = $pdo->prepare("
  INSERT INTO Notification (StaffID, Title, Message, NotificationTypeID, RelatedEntityType, RelatedEntityID, IsRead, CreatedAt)
  VALUES (?, 'Preclose Request', ?, 1, 'Loan', ?, 0, GETDATE())
");
foreach (array_column($bmStmt->fetchAll(), 'StaffID') as $sid) {
    $notifyIns->execute([$sid, "Preclose requested for loan {$loan['LoanNo']}.", $loanId]);
}

json_response(true, ['loan_approval_id' => $newId], 'Preclose request submitted');

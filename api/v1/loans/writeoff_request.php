<?php
/**
 * POST /api/v1/loans/writeoff_request.php
 * Body: { loan_id, reason? }
 * A field/branch request that a loan be written off -- only allowed once
 * it's 90+ days overdue (the same NPA threshold api/v1/reports/npa_loans.php
 * lists against). Admin reviews and decides via loan_writeoffs.php (web).
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();
$body = get_json_body();
$loanId = (int)($body['loan_id'] ?? 0);
$reason = trim((string)($body['reason'] ?? '')) ?: null;

if ($loanId <= 0) {
    json_response(false, null, 'loan_id is required', 422);
}

$loanStmt = $pdo->prepare("
  SELECT l.LoanID, l.LoanOfficerStaffID, l.BranchID, stg.StageName
  FROM Loan l JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
  WHERE l.LoanID = ?
");
$loanStmt->execute([$loanId]);
$loan = $loanStmt->fetch();
if (!$loan) {
    json_response(false, null, 'Loan not found', 404);
}
assert_loan_access($loan, $claims);

if ($loan['StageName'] !== 'Active') {
    json_response(false, null, 'Only an Active loan can be written off', 422);
}

$overdueStmt = $pdo->prepare("
  SELECT MIN(DueDate) AS OldestUnpaidDue, SUM(PrincipalDue) AS Outstanding
  FROM Repayment_Schedule WHERE LoanID = ? AND IsPaid = 0
");
$overdueStmt->execute([$loanId]);
$overdue = $overdueStmt->fetch();
$daysOverdue = $overdue['OldestUnpaidDue'] ? (int)((strtotime(date('Y-m-d')) - strtotime($overdue['OldestUnpaidDue'])) / 86400) : 0;

if ($daysOverdue <= 90) {
    json_response(false, null, 'This loan is not yet 90+ days overdue', 422);
}

$pendingStmt = $pdo->prepare("SELECT 1 FROM Loan_WriteOff_Request WHERE LoanID = ? AND Status = 'Pending'");
$pendingStmt->execute([$loanId]);
if ($pendingStmt->fetchColumn()) {
    json_response(false, null, 'A write-off request is already pending for this loan', 409);
}

$newId = insert_and_get_id($pdo, "INSERT INTO Loan_WriteOff_Request (LoanID, RequestedByStaffID, RequestReason, OutstandingAmount, Status)
    VALUES (?,?,?,?, 'Pending')", [
    $loanId,
    (int)$claims['StaffID'],
    $reason,
    (float)($overdue['Outstanding'] ?? 0),
], 'RequestID');

json_response(true, ['request_id' => $newId], 'Write-off request submitted');

<?php
/**
 * POST /api/v1/loans/tvr_create.php
 * Body: { loan_id, contact_person_name, tvr_date, tvr_status_id, verification_notes? }
 *
 * Records a Tele-Verification call for a loan (TVR_Details) -- one per
 * loan; a second submission for the same loan_id updates the existing row
 * instead of creating a duplicate.
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../../includes/permission_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();

if (!role_has_permission($pdo, $claims['RoleID'] ?? null, 'conduct_tvr')) {
    json_response(false, null, 'You do not have permission to conduct TVR', 403);
}

$body = get_json_body();
$loanId = (int)($body['loan_id'] ?? 0);
$contactPersonName = trim((string)($body['contact_person_name'] ?? ''));
$tvrDate = trim((string)($body['tvr_date'] ?? ''));
$tvrStatusId = (int)($body['tvr_status_id'] ?? 0);
$notes = trim((string)($body['verification_notes'] ?? '')) ?: null;

if ($loanId <= 0 || $contactPersonName === '' || $tvrDate === '' || $tvrStatusId <= 0) {
    json_response(false, null, 'loan_id, contact_person_name, tvr_date and tvr_status_id are required', 422);
}

$loanStmt = $pdo->prepare("SELECT LoanID, BranchID, LoanOfficerStaffID FROM Loan WHERE LoanID = ?");
$loanStmt->execute([$loanId]);
$loan = $loanStmt->fetch();
if (!$loan) {
    json_response(false, null, 'Loan not found', 404);
}
assert_loan_access($loan, $claims);

$statusCheck = $pdo->prepare("SELECT 1 FROM TVR_Status_Master WHERE TVRStatusID = ?");
$statusCheck->execute([$tvrStatusId]);
if (!$statusCheck->fetchColumn()) {
    json_response(false, null, 'Invalid tvr_status_id', 422);
}

$existingStmt = $pdo->prepare("SELECT TVRID FROM TVR_Details WHERE LoanID = ?");
$existingStmt->execute([$loanId]);
$existingId = $existingStmt->fetchColumn();

if ($existingId) {
    $pdo->prepare("
      UPDATE TVR_Details
      SET ContactPersonName = ?, TVRDate = ?, TVRStatusID = ?, VerificationNotes = ?, ConductedByStaffID = ?
      WHERE TVRID = ?
    ")->execute([$contactPersonName, $tvrDate, $tvrStatusId, $notes, (int)$claims['StaffID'], $existingId]);
    $tvrId = (int)$existingId;
} else {
    $tvrId = insert_and_get_id($pdo, "
      INSERT INTO TVR_Details (LoanID, ConductedByStaffID, TVRDate, ContactPersonName, VerificationNotes, TVRStatusID, CreatedAt)
      VALUES (?, ?, ?, ?, ?, ?, GETDATE())
    ", [$loanId, (int)$claims['StaffID'], $tvrDate, $contactPersonName, $notes, $tvrStatusId], 'TVRID');
}

json_response(true, ['tvr_id' => $tvrId], 'TVR recorded');

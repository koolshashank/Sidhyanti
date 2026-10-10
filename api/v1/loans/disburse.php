<?php
/**
 * POST /api/v1/loans/disburse.php
 * Body: { loan_id, disbursed_amount, disbursement_ref_no? }
 * Gated by "disburse_loan". Only allowed while the loan is at the
 * 'Approved' stage; moves it to 'Disbursed' and generates the repayment
 * schedule via the same helper loan_view.php's stage dropdown uses.
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../includes/api_error.php';
require_once __DIR__ . '/../../../includes/permission_helper.php';
require_once __DIR__ . '/../../../includes/loan_schedule_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();

if (!role_has_permission($pdo, $claims['RoleID'] ?? null, 'disburse_loan')) {
    json_response(false, null, 'You do not have permission to disburse a loan', 403);
}

$body = get_json_body();
$loanId = (int)($body['loan_id'] ?? 0);
$disbursedAmount = (float)($body['disbursed_amount'] ?? 0);
$refNo = trim((string)($body['disbursement_ref_no'] ?? '')) ?: null;
$mode = trim((string)($body['disbursement_mode'] ?? ''));
$confirmAccountNo = trim((string)($body['confirm_account_number'] ?? ''));

if ($loanId <= 0 || $disbursedAmount <= 0) {
    json_response(false, null, 'loan_id and a positive disbursed_amount are required', 422);
}

if (!in_array($mode, ['Cash', 'NEFT', 'RTGS'], true)) {
    json_response(false, null, 'disbursement_mode must be Cash, NEFT or RTGS', 422);
}

$loanStmt = $pdo->prepare("
  SELECT l.LoanID, l.LoanNo, l.BranchID, l.MemberID, l.LoanOfficerStaffID, l.DisbursementPhotoPath, stg.StageName
  FROM Loan l LEFT JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
  WHERE l.LoanID = ?
");
$loanStmt->execute([$loanId]);
$loan = $loanStmt->fetch();
if (!$loan) {
    json_response(false, null, 'Loan not found', 404);
}
assert_loan_access($loan, $claims);

if ($loan['StageName'] !== 'Approved') {
    json_response(false, null, 'Only a loan at the Approved stage can be disbursed', 409);
}

if (empty($loan['DisbursementPhotoPath'])) {
    json_response(false, null, 'Capture the customer\'s photo before disbursing this loan', 409);
}

if ($mode !== 'Cash') {
    $bankStmt = $pdo->prepare("SELECT AccountNumber FROM Member_Bank_Details WHERE MemberID = ?");
    $bankStmt->execute([(int)$loan['MemberID']]);
    $actualAccountNo = trim((string)$bankStmt->fetchColumn());
    if ($actualAccountNo === '') {
        json_response(false, null, 'No bank account on file for this member -- cannot disburse via NEFT/RTGS', 409);
    }
    if ($confirmAccountNo === '' || $confirmAccountNo !== $actualAccountNo) {
        json_response(false, null, 'The account number you entered does not match the member\'s bank account on file', 422);
    }
}

$pdo->beginTransaction();
try {
    $disbursedStageId = $pdo->query("SELECT StageID FROM Loan_Stage_Master WHERE StageName = 'Disbursed'")->fetchColumn();
    $pdo->prepare("
      UPDATE Loan
      SET CurrentStageID = ?, DisbursedAmount = ?, DisbursedByStaffID = ?, DisbursementRefNo = ?, DisbursementMode = ?,
          UpdatedAt = GETDATE(), UpdatedBy = ?
      WHERE LoanID = ?
    ")->execute([$disbursedStageId, $disbursedAmount, (int)$claims['StaffID'], $refNo, $mode, (int)$claims['StaffID'], $loanId]);

    generate_repayment_schedule($pdo, $loanId);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('loans/disburse.php failed: ' . $e->getMessage());
    json_response(false, null, 'Failed to disburse loan', 500);
}

if ($loan['LoanOfficerStaffID']) {
    $pdo->prepare("
      INSERT INTO Notification (StaffID, Title, Message, NotificationTypeID, RelatedEntityType, RelatedEntityID, IsRead, CreatedAt)
      VALUES (?, 'Loan Disbursed', ?, 1, 'Loan', ?, 0, GETDATE())
    ")->execute([(int)$loan['LoanOfficerStaffID'], "Loan {$loan['LoanNo']} has been disbursed.", $loanId]);
}

json_response(true, ['loan_id' => $loanId], 'Loan disbursed');

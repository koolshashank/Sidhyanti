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

if ($loanId <= 0 || $disbursedAmount <= 0) {
    json_response(false, null, 'loan_id and a positive disbursed_amount are required', 422);
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

if ($loan['StageName'] !== 'Approved') {
    json_response(false, null, 'Only a loan at the Approved stage can be disbursed', 409);
}

$pdo->beginTransaction();
try {
    $disbursedStageId = $pdo->query("SELECT StageID FROM Loan_Stage_Master WHERE StageName = 'Disbursed'")->fetchColumn();
    $pdo->prepare("
      UPDATE Loan
      SET CurrentStageID = ?, DisbursedAmount = ?, DisbursedByStaffID = ?, DisbursementRefNo = ?,
          UpdatedAt = GETDATE(), UpdatedBy = ?
      WHERE LoanID = ?
    ")->execute([$disbursedStageId, $disbursedAmount, (int)$claims['StaffID'], $refNo, (int)$claims['StaffID'], $loanId]);

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

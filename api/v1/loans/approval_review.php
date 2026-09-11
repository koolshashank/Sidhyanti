<?php
/**
 * POST /api/v1/loans/approval_review.php
 * Body: { loan_approval_id, action: 'approve'|'reject', remarks? }
 *
 * Handles both Loan_Approval.RequestType values:
 *  - 'Proposal': advisory sign-off only -- writes Status, does not touch
 *    the loan's stage (GRT-pass auto-advance is unaffected either way).
 *  - 'Preclose': on approve, also moves the loan to the 'Preclosed' stage
 *    (same effect as the manual stage dropdown in loan_view.php).
 *
 * Gated by "approve_loan_proposal"/"approve_loan_preclose" respectively,
 * branch-scoped via assert_loan_access.
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';
require_once __DIR__ . '/../../includes/api_error.php';
require_once __DIR__ . '/../../../includes/permission_helper.php';
require_once __DIR__ . '/../../../includes/loan_approval_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();
$body = get_json_body();

$loanApprovalId = (int)($body['loan_approval_id'] ?? 0);
$action = trim((string)($body['action'] ?? ''));
$remarks = trim((string)($body['remarks'] ?? '')) ?: null;

if ($loanApprovalId <= 0) {
    json_response(false, null, 'loan_approval_id is required', 422);
}
if (!in_array($action, ['approve', 'reject'], true)) {
    json_response(false, null, 'action must be approve or reject', 422);
}

$laStmt = $pdo->prepare("
  SELECT la.LoanApprovalID, la.LoanID, la.RequestType, la.RequestedByStaffID,
         l.LoanNo, l.BranchID, l.LoanOfficerStaffID, l.LoanAmountSanctioned, l.LoanAmountApplied
  FROM Loan_Approval la JOIN Loan l ON la.LoanID = l.LoanID
  WHERE la.LoanApprovalID = ?
");
$laStmt->execute([$loanApprovalId]);
$la = $laStmt->fetch();
if (!$la) {
    json_response(false, null, 'Approval request not found', 404);
}
assert_loan_access($la, $claims);

$permissionKey = $la['RequestType'] === 'Preclose' ? 'approve_loan_preclose' : 'approve_loan_proposal';
if (!role_has_permission($pdo, $claims['RoleID'] ?? null, $permissionKey)) {
    json_response(false, null, 'You do not have permission to review this request', 403);
}

if ($la['RequestType'] === 'Proposal') {
    $loanAmount = to_money($la['LoanAmountSanctioned'] ?: $la['LoanAmountApplied']);
    $violation = loan_proposal_amount_violation($claims, $loanAmount);
    if ($violation !== null) {
        json_response(false, null, $violation, 403);
    }
}

$newStatus = $action === 'approve' ? 'Approved' : 'Rejected';

$pdo->beginTransaction();
try {
    $update = $pdo->prepare("
      UPDATE Loan_Approval
      SET Status = ?, RemarksByApprover = ?, ReviewedByStaffID = ?, ReviewedAt = GETDATE()
      WHERE LoanApprovalID = ? AND Status = 'Pending'
    ");
    $update->execute([$newStatus, $remarks, (int)$claims['StaffID'], $loanApprovalId]);
    if ($update->rowCount() !== 1) {
        throw new ApiError(409, 'This request was already reviewed');
    }

    if ($la['RequestType'] === 'Preclose' && $action === 'approve') {
        $preclosedStageId = $pdo->query("SELECT StageID FROM Loan_Stage_Master WHERE StageName = 'Preclosed'")->fetchColumn();
        $pdo->prepare("UPDATE Loan SET CurrentStageID = ?, UpdatedAt = GETDATE(), UpdatedBy = ? WHERE LoanID = ?")
            ->execute([$preclosedStageId, (int)$claims['StaffID'], $la['LoanID']]);
    }

    $pdo->commit();
} catch (ApiError $e) {
    $pdo->rollBack();
    json_response(false, null, $e->getMessage(), $e->httpCode);
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('loans/approval_review.php failed: ' . $e->getMessage());
    json_response(false, null, 'Failed to review request', 500);
}

$label = $la['RequestType'] === 'Preclose' ? 'Preclose request' : 'Loan proposal';
$msg = "$label for loan {$la['LoanNo']} was " . strtolower($newStatus) . '.';
if ($remarks) {
    $msg .= ' Remarks: ' . $remarks;
}
$pdo->prepare("
  INSERT INTO Notification (StaffID, Title, Message, NotificationTypeID, RelatedEntityType, RelatedEntityID, IsRead, CreatedAt)
  VALUES (?, ?, ?, 1, 'Loan', ?, 0, GETDATE())
")->execute([(int)$la['RequestedByStaffID'], "$label Reviewed", $msg, $la['LoanID']]);

json_response(true, ['loan_approval_id' => $loanApprovalId, 'status' => $newStatus], "$label " . strtolower($newStatus));

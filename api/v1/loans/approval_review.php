<?php
/**
 * POST /api/v1/loans/approval_review.php
 * Body: { loan_approval_id, action: 'approve'|'reject', remarks? }
 *
 * Handles both Loan_Approval.RequestType values:
 *  - 'Proposal': two-step above the BM limit (see loan_approval_helper.php)
 *    -- a Branch Manager reviews first; at/under the limit their approval
 *    is final, above it their approval only forwards the row to every Area
 *    Manager covering that branch for final sign-off. Either stage's
 *    rejection is terminal. Advisory only either way -- doesn't touch the
 *    loan's stage (GRT-pass auto-advance is unaffected).
 *  - 'Preclose': single-step, unchanged -- on approve, also moves the loan
 *    to the 'Preclosed' stage (same effect as the manual stage dropdown in
 *    loan_view.php).
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
  SELECT la.LoanApprovalID, la.LoanID, la.RequestType, la.RequestedByStaffID, la.Status,
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

try {
    $newStatus = review_loan_approval($pdo, $la, $action, $remarks, $claims);
} catch (LoanApprovalReviewException $e) {
    json_response(false, null, $e->getMessage(), $e->httpCode);
}

json_response(true, ['loan_approval_id' => $loanApprovalId, 'status' => $newStatus], 'Reviewed');

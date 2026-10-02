<?php
/**
 * POST /api/v1/loans/grt_create.php (multipart/form-data)
 * Fields: loan_id, status ('Pass'|'Fail'), grt_date (YYYY-MM-DD), finance_amount_verified?,
 *         remarks?, document (file, optional -- image/PDF)
 *
 * Gated by the "conduct_grt" role permission (Role Permissions, configurable
 * by Admin). GRT can never be conducted on the same date as the loan's
 * ApplicationDate (proposal entry date) -- this is a hard business rule, not
 * just a UI hint, so it's enforced here regardless of what the app sent.
 *
 * On a Pass, if the loan is currently at the "GRT Pending" stage, it's
 * auto-advanced to "Approved" (and ApprovalDate set) -- mirrors the
 * auto-schedule-generation behaviour loan_view.php already has for the
 * Disbursed/Active transition. A Fail, or a loan already past GRT Pending,
 * leaves the stage untouched.
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../includes/api_error.php';
require_once __DIR__ . '/../../includes/document_storage_helper.php';
require_once __DIR__ . '/../../../includes/permission_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();

if (!role_has_permission($pdo, $claims['RoleID'] ?? null, 'conduct_grt')) {
    json_response(false, null, 'You do not have permission to conduct GRT', 403);
}

$loanId = (int)($_POST['loan_id'] ?? 0);
$status = trim((string)($_POST['status'] ?? ''));
$grtDate = trim((string)($_POST['grt_date'] ?? ''));

if ($loanId <= 0) {
    json_response(false, null, 'loan_id is required', 422);
}
if (!in_array($status, ['Pass', 'Fail'], true)) {
    json_response(false, null, 'status must be Pass or Fail', 422);
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $grtDate)) {
    json_response(false, null, 'grt_date (YYYY-MM-DD) is required', 422);
}

$loanStmt = $pdo->prepare("SELECT LoanID, BranchID, LoanOfficerStaffID, CurrentStageID, ApplicationDate FROM Loan WHERE LoanID = ?");
$loanStmt->execute([$loanId]);
$loan = $loanStmt->fetch();
if (!$loan) {
    json_response(false, null, 'Loan not found', 404);
}
assert_loan_access($loan, $claims);

// GRT can never happen on the same date the proposal itself was entered.
if ($loan['ApplicationDate'] !== null && substr((string)$loan['ApplicationDate'], 0, 10) === $grtDate) {
    json_response(false, null, 'GRT cannot be conducted on the same date as the loan proposal entry. Please try again on a different date.', 409);
}

if (empty($_FILES['document']) || ($_FILES['document']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    json_response(false, null, 'GRT photo is required', 422);
}

try {
    $documentUrl = store_document_upload($_FILES['document'] ?? [], 'GRT', (string)$loanId, 'grt');
} catch (ApiError $e) {
    json_response(false, null, $e->getMessage(), $e->httpCode);
}

$financeAmount = $_POST['finance_amount_verified'] ?? null;
$financeAmount = ($financeAmount === null || $financeAmount === '') ? null : (float)$financeAmount;
$remarks = trim((string)($_POST['remarks'] ?? '')) ?: null;

$grtStatusId = $pdo->prepare("SELECT GRTStatusID FROM GRT_Status_Master WHERE GRTStatusName = ?");
$grtStatusId->execute([$status === 'Pass' ? 'P' : 'F']);
$grtStatusId = $grtStatusId->fetchColumn();

$pdo->beginTransaction();
try {
    $grtId = insert_and_get_id($pdo, "INSERT INTO GRT_Details
        (LoanID, ConductedByStaffID, GRTStatusID, GRTDate, FinanceAmountVerified, DocumentFilePath, EnrollmentGRTFileName, Remarks)
        VALUES (?,?,?,?,?,?,?,?)", [
        $loanId,
        (int)$claims['StaffID'],
        $grtStatusId,
        $grtDate,
        $financeAmount,
        $documentUrl,
        $documentUrl,
        $remarks,
    ], 'GRTID');

    $advanced = false;
    if ($status === 'Pass') {
        $currentStageName = $pdo->prepare("SELECT StageName FROM Loan_Stage_Master WHERE StageID = ?");
        $currentStageName->execute([$loan['CurrentStageID']]);
        if ($currentStageName->fetchColumn() === 'GRT Pending') {
            $approvedStageId = $pdo->query("SELECT StageID FROM Loan_Stage_Master WHERE StageName = 'Approved'")->fetchColumn();
            $pdo->prepare("UPDATE Loan SET CurrentStageID=?, ApprovalDate=COALESCE(ApprovalDate, CAST(GETDATE() AS DATE)), UpdatedAt=GETDATE(), UpdatedBy=? WHERE LoanID=?")
                ->execute([$approvedStageId, (int)$claims['StaffID'], $loanId]);
            $advanced = true;
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('loans/grt_create.php failed: ' . $e->getMessage());
    json_response(false, null, 'Failed to save GRT record', 500);
}

json_response(true, [
    'grt_id'          => $grtId,
    'stage_advanced'  => $advanced,
], 'GRT recorded successfully');

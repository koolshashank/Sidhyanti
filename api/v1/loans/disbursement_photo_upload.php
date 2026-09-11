<?php
/**
 * POST /api/v1/loans/disbursement_photo_upload.php (multipart/form-data)
 * Fields: loan_id, photo (file)
 *
 * Captures the customer's photo at disbursement time -- required before
 * disburse.php will let the loan move to 'Disbursed' (anti-fraud: proves
 * the customer was physically present). Only allowed while the loan is
 * still at the 'Approved' stage, same as disburse.php itself.
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

if (!role_has_permission($pdo, $claims['RoleID'] ?? null, 'disburse_loan')) {
    json_response(false, null, 'You do not have permission to disburse loans', 403);
}

$loanId = (int)($_POST['loan_id'] ?? 0);
if ($loanId <= 0) {
    json_response(false, null, 'loan_id is required', 422);
}

$loanStmt = $pdo->prepare("
  SELECT l.LoanID, l.BranchID, l.LoanOfficerStaffID, stg.StageName
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
    json_response(false, null, 'Only a loan at the Approved stage can have a disbursement photo captured', 409);
}

try {
    $photoUrl = store_document_upload($_FILES['photo'] ?? [], 'Disbursement_Photo', (string)$loanId, 'disb');
} catch (ApiError $e) {
    json_response(false, null, $e->getMessage(), $e->httpCode);
}
if ($photoUrl === null) {
    json_response(false, null, 'photo file is required', 422);
}

$pdo->prepare("UPDATE Loan SET DisbursementPhotoPath = ? WHERE LoanID = ?")->execute([$photoUrl, $loanId]);

json_response(true, ['photo_url' => $photoUrl], 'Photo captured');

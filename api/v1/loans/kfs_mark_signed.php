<?php
/**
 * POST /api/v1/loans/kfs_mark_signed.php
 * Body: { loan_id }
 * Records that the printed KFS was signed by the client and collected by
 * the branch -- the physical paper itself is filed at the branch, nothing
 * is uploaded or captured here.
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();
$body = get_json_body();
$loanId = (int)($body['loan_id'] ?? 0);
if ($loanId <= 0) {
    json_response(false, null, 'loan_id is required', 422);
}

$loanStmt = $pdo->prepare("SELECT LoanID, LoanOfficerStaffID, BranchID FROM Loan WHERE LoanID = ?");
$loanStmt->execute([$loanId]);
$loan = $loanStmt->fetch();
if (!$loan) {
    json_response(false, null, 'Loan not found', 404);
}
assert_loan_access($loan, $claims);

$pdo->prepare("UPDATE Loan SET KFSSignedCollected = 1, KFSSignedAt = GETDATE(), KFSSignedByStaffID = ? WHERE LoanID = ?")
    ->execute([(int)$claims['StaffID'], $loanId]);

json_response(true, ['loan_id' => $loanId], 'Marked as signed and collected');

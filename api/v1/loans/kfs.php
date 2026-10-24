<?php
/**
 * GET /api/v1/loans/kfs.php?loan_id={id}
 * The Key Fact Sheet for a loan -- see includes/kfs_helper.php. Available
 * once a loan reaches 'Approved' (or any later stage); the caller decides
 * whether to show the screen based on `stage`.
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../../includes/kfs_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();
$loanId = (int)($_GET['loan_id'] ?? 0);
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

$kfs = build_kfs_data($pdo, $loanId);
if (!$kfs) {
    json_response(false, null, 'Loan not found', 404);
}

json_response(true, $kfs);

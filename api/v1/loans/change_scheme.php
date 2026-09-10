<?php
/**
 * POST /api/v1/loans/change_scheme.php
 * Body: { "loan_id": ..., "new_scheme_id": ... }
 *
 * Mirrors loan_view.php's web "Change Scheme" action: gated by the
 * "change_loan_scheme" role permission (Role Permissions, configurable by
 * Admin) and only allowed before disbursement, since changing the
 * amortization inputs after a repayment schedule already exists would
 * desync the schedule from the loan.
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';
require_once __DIR__ . '/../../../includes/permission_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();
$body = get_json_body();
$loanId = (int)($body['loan_id'] ?? 0);
$newSchemeId = (int)($body['new_scheme_id'] ?? 0);

if ($loanId <= 0 || $newSchemeId <= 0) {
    json_response(false, null, 'loan_id and new_scheme_id are required', 422);
}

if (!role_has_permission($pdo, $claims['RoleID'] ?? null, 'change_loan_scheme')) {
    json_response(false, null, 'You do not have permission to change loan schemes', 403);
}

$stmt = $pdo->prepare("SELECT LoanID, LoanOfficerStaffID, BranchID, SchemeID, DisbursementDate FROM Loan WHERE LoanID = ?");
$stmt->execute([$loanId]);
$loan = $stmt->fetch();
if (!$loan) {
    json_response(false, null, 'Loan not found', 404);
}
assert_loan_access($loan, $claims);

if ($loan['DisbursementDate'] !== null) {
    json_response(false, null, 'This loan has already been disbursed and its scheme can no longer be changed', 409);
}

$scheduleCount = $pdo->prepare("SELECT COUNT(*) FROM Repayment_Schedule WHERE LoanID = ?");
$scheduleCount->execute([$loanId]);
if ((int)$scheduleCount->fetchColumn() > 0) {
    json_response(false, null, 'This loan already has a repayment schedule and its scheme can no longer be changed', 409);
}

$newScheme = $pdo->prepare("SELECT SchemeID, SchemeName, TenureMonths, DefaultLoanAmount, InterestRatePercent FROM Loan_Scheme WHERE SchemeID = ? AND IsActive = 1");
$newScheme->execute([$newSchemeId]);
$newScheme = $newScheme->fetch();
if (!$newScheme) {
    json_response(false, null, 'Selected scheme was not found or is inactive', 404);
}

$oldSchemeName = $pdo->prepare("SELECT SchemeName FROM Loan_Scheme WHERE SchemeID = ?");
$oldSchemeName->execute([$loan['SchemeID']]);
$oldSchemeName = $oldSchemeName->fetchColumn();

$pdo->prepare("
    UPDATE Loan SET SchemeID=?, LoanAmountApplied=?, LoanAmountSanctioned=CASE WHEN LoanAmountSanctioned IS NOT NULL THEN ? ELSE NULL END,
        TenureMonths=?, InterestRatePercent=?, UpdatedAt=GETDATE(), UpdatedBy=? WHERE LoanID=?
")->execute([
    $newScheme['SchemeID'], $newScheme['DefaultLoanAmount'], $newScheme['DefaultLoanAmount'],
    $newScheme['TenureMonths'], $newScheme['InterestRatePercent'], $claims['StaffID'], $loanId,
]);

$pdo->prepare("INSERT INTO Note (RelatedEntityType, RelatedEntityID, StaffID, NoteText) VALUES ('Loan',?,?,?)")
    ->execute([$loanId, $claims['StaffID'],
        'Scheme changed from ' . ($oldSchemeName ?: 'None') . ' to ' . $newScheme['SchemeName']
        . ' — amount and tenure updated accordingly.']);

json_response(true, [
    'loan_id'               => $loanId,
    'scheme_id'             => (int)$newScheme['SchemeID'],
    'scheme_name'           => $newScheme['SchemeName'],
    'loan_amount'           => to_money($newScheme['DefaultLoanAmount']),
    'tenure_months'         => (int)$newScheme['TenureMonths'],
    'interest_rate_percent' => to_money($newScheme['InterestRatePercent']),
], 'Scheme changed successfully');

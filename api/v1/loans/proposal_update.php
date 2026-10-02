<?php
/**
 * POST /api/v1/loans/proposal_update.php
 * Body: { loan_id, scheme_id, amount, purpose_id?, emi_amount?,
 *         self_income?, husband_son_income?, other_income?,
 *         house_expenses?, mfi_expenses?, business_expenses?, source_of_income?,
 *         ifsc_code?, bank_name?, bank_branch?, bank_address?, account_number?, beneficiary_name? }
 *
 * Lets a reviewer (Branch Manager / Area Manager) correct a loan proposal's
 * details -- product/amount/purpose/EMI, income assessment, bank details --
 * while it's still under review, before approving or rejecting it. Gated by
 * the same "approve_loan_proposal" permission as the approval action itself
 * (only a reviewer edits here, not the original requester), and only
 * allowed while a Proposal-type Loan_Approval row for this loan is still
 * 'Pending' or 'BM Approved' (i.e. not yet finally decided).
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../../includes/permission_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();

if (!role_has_permission($pdo, $claims['RoleID'] ?? null, 'approve_loan_proposal')) {
    json_response(false, null, 'You do not have permission to edit this proposal', 403);
}

$body = get_json_body();
$loanId = (int)($body['loan_id'] ?? 0);
$schemeId = (int)($body['scheme_id'] ?? 0);
$amount = (float)($body['amount'] ?? 0);

if ($loanId <= 0) {
    json_response(false, null, 'loan_id is required', 422);
}
if ($schemeId <= 0) {
    json_response(false, null, 'scheme_id (Product) is required', 422);
}
if ($amount <= 0) {
    json_response(false, null, 'A valid amount is required', 422);
}

$loanStmt = $pdo->prepare("SELECT LoanID, MemberID, BranchID, LoanOfficerStaffID FROM Loan WHERE LoanID = ?");
$loanStmt->execute([$loanId]);
$loan = $loanStmt->fetch();
if (!$loan) {
    json_response(false, null, 'Loan not found', 404);
}
assert_loan_access($loan, $claims);

$laStmt = $pdo->prepare("
  SELECT TOP 1 LoanApprovalID, Status FROM Loan_Approval
  WHERE LoanID = ? AND RequestType = 'Proposal' ORDER BY LoanApprovalID DESC
");
$laStmt->execute([$loanId]);
$la = $laStmt->fetch();
if (!$la || !in_array($la['Status'], ['Pending', 'BM Approved'], true)) {
    json_response(false, null, 'This proposal is no longer under review', 409);
}

$scheme = $pdo->prepare("SELECT SchemeID, TenureMonths, InterestRatePercent FROM Loan_Scheme WHERE SchemeID = ? AND IsActive = 1");
$scheme->execute([$schemeId]);
$scheme = $scheme->fetch();
if (!$scheme) {
    json_response(false, null, 'Selected product was not found or is inactive', 422);
}

$purposeId = (int)($body['purpose_id'] ?? 0) ?: null;
$emiAmount = isset($body['emi_amount']) && $body['emi_amount'] !== '' ? (float)$body['emi_amount'] : null;

function opt_num3($body, $key) {
    $v = $body[$key] ?? null;
    return ($v === null || $v === '') ? null : (float)$v;
}

$pdo->beginTransaction();
try {
    $pdo->prepare("
      UPDATE Loan
      SET SchemeID=?, LoanAmountApplied=?, TenureMonths=?, InterestRatePercent=?, PurposeID=?, EMIAmount=?, UpdatedAt=GETDATE(), UpdatedBy=?
      WHERE LoanID=?
    ")->execute([
        $schemeId, $amount, $scheme['TenureMonths'], $scheme['InterestRatePercent'], $purposeId, $emiAmount, (int)$claims['StaffID'], $loanId,
    ]);

    $selfIncome = opt_num3($body, 'self_income');
    $husbandSonIncome = opt_num3($body, 'husband_son_income');
    $otherIncome = opt_num3($body, 'other_income');
    $residentIncome = opt_num3($body, 'resident_income');
    $houseExpenses = opt_num3($body, 'house_expenses');
    $mfiExpenses = opt_num3($body, 'mfi_expenses');
    $businessExpenses = opt_num3($body, 'business_expenses');
    $sourceOfIncome = trim((string)($body['source_of_income'] ?? '')) ?: null;

    $incomeParts = array_filter([$selfIncome, $husbandSonIncome, $otherIncome, $residentIncome], fn($v) => $v !== null);
    $totalIncome = $incomeParts ? array_sum($incomeParts) : null;
    $expenseParts = array_filter([$houseExpenses, $mfiExpenses, $businessExpenses], fn($v) => $v !== null);
    $totalExpenses = $expenseParts ? array_sum($expenseParts) : null;
    $disposableIncome = ($totalIncome !== null || $totalExpenses !== null) ? ($totalIncome ?? 0) - ($totalExpenses ?? 0) : null;

    $assessmentParams = [
        $selfIncome, $husbandSonIncome, $otherIncome, $residentIncome, $totalIncome,
        $houseExpenses, $mfiExpenses, $businessExpenses, $totalExpenses,
        $disposableIncome, $sourceOfIncome,
    ];

    $existingAssessmentId = $pdo->prepare("SELECT AssessmentID FROM Loan_Income_Assessment WHERE LoanID = ?");
    $existingAssessmentId->execute([$loanId]);
    $existingAssessmentId = $existingAssessmentId->fetchColumn();

    if ($existingAssessmentId) {
        $pdo->prepare("
          UPDATE Loan_Income_Assessment SET
            SelfIncomeMonthly=?, HusbandSonIncomeMonthly=?, OtherIncomeMonthly=?, ResidentIncomeMonthly=?, TotalIncomeMonthly=?,
            HouseExpensesMonthly=?, MFIExpensesMonthly=?, BusinessExpensesMonthly=?, TotalExpensesMonthly=?,
            DisposableIncomeMonthly=?, SourceOfIncome=?
          WHERE AssessmentID=?
        ")->execute([...$assessmentParams, $existingAssessmentId]);
    } else {
        $pdo->prepare("
          INSERT INTO Loan_Income_Assessment
            (LoanID, SelfIncomeMonthly, HusbandSonIncomeMonthly, OtherIncomeMonthly, ResidentIncomeMonthly, TotalIncomeMonthly,
             HouseExpensesMonthly, MFIExpensesMonthly, BusinessExpensesMonthly, TotalExpensesMonthly,
             DisposableIncomeMonthly, SourceOfIncome, AssessmentDate)
          VALUES (?,?,?,?,?,?,?,?,?,?,?,?, CAST(GETDATE() AS DATE))
        ")->execute([$loanId, ...$assessmentParams]);
    }

    $ifscCode = trim((string)($body['ifsc_code'] ?? '')) ?: null;
    $bankName = trim((string)($body['bank_name'] ?? '')) ?: null;
    $bankBranch = trim((string)($body['bank_branch'] ?? '')) ?: null;
    $bankAddress = trim((string)($body['bank_address'] ?? '')) ?: null;
    $accountNumber = trim((string)($body['account_number'] ?? '')) ?: null;
    $beneficiaryName = trim((string)($body['beneficiary_name'] ?? '')) ?: null;

    if ($ifscCode !== null || $bankName !== null || $accountNumber !== null || $beneficiaryName !== null) {
        $existingBankStmt = $pdo->prepare("SELECT BankDetailID FROM Member_Bank_Details WHERE MemberID = ?");
        $existingBankStmt->execute([$loan['MemberID']]);
        $existingBankId = $existingBankStmt->fetchColumn();
        $bankParams = [$bankName, $ifscCode, $bankBranch, $bankAddress, $accountNumber, $beneficiaryName];
        if ($existingBankId) {
            $pdo->prepare("
              UPDATE Member_Bank_Details
              SET BankName=?, IFSCCode=?, BankBranch=?, BankAddress=?, AccountNumber=?, BeneficiaryName=?, UpdatedAt=GETDATE(), UpdatedBy=?
              WHERE BankDetailID=?
            ")->execute([...$bankParams, (int)$claims['StaffID'], $existingBankId]);
        } else {
            $pdo->prepare("
              INSERT INTO Member_Bank_Details (MemberID, BankName, IFSCCode, BankBranch, BankAddress, AccountNumber, BeneficiaryName, CreatedBy)
              VALUES (?,?,?,?,?,?,?,?)
            ")->execute([$loan['MemberID'], ...$bankParams, (int)$claims['StaffID']]);
        }
    }

    $pdo->prepare("INSERT INTO Note (RelatedEntityType, RelatedEntityID, StaffID, NoteText) VALUES ('Loan', ?, ?, ?)")
        ->execute([$loanId, (int)$claims['StaffID'], 'Loan proposal details updated during review.']);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('loans/proposal_update.php failed: ' . $e->getMessage());
    json_response(false, null, 'Failed to save changes', 500);
}

json_response(true, ['loan_id' => $loanId], 'Proposal updated');

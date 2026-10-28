<?php
/**
 * POST /api/v1/loans/create.php ("Loan Proposal" -- captured right after
 * co-borrower KYC in the mobile sourcing flow)
 * Body: { member_id, scheme_id, amount?, purpose_id?, emi_amount?,
 *         self_income?, husband_son_income?, other_income?, resident_income?,
 *         house_expenses?, mfi_expenses?, business_expenses?, source_of_income?,
 *         ifsc_code?, bank_name?, bank_branch?, bank_address?, account_number?, beneficiary_name? }
 *
 * Only member_id and scheme_id are required -- scheme_id is what supplies
 * Loan's NOT NULL LoanAmountApplied/TenureMonths/InterestRatePercent
 * (the Product picked on the mobile form). Everything else is optional,
 * matching the same dev-time leniency as members/create.php and
 * coborrowers/create.php.
 *
 * `amount`, if sent, overrides the scheme's default amount -- but only for a
 * reloan (a member with a completed prior loan cycle matching a
 * Reloan_Eligibility_Rule) and only up to that member's server-computed
 * eligible amount; see compute_reloan_eligibility() in reloan_helper.php.
 *
 * Writes two rows: Loan (the application itself) and Loan_Income_Assessment
 * (the household income/expense assessment captured alongside it). Also
 * upserts Member_Bank_Details if any bank field was sent -- one row per
 * member, same table the web CRM's member_view.php bank-details modal
 * already reads/writes.
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../includes/reloan_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();
$body = get_json_body();

function opt_num($body, $key) {
    $v = $body[$key] ?? null;
    return ($v === null || $v === '') ? null : (float)$v;
}

$memberId = (int)($body['member_id'] ?? 0);
$schemeId = (int)($body['scheme_id'] ?? 0);
if ($memberId <= 0) {
    json_response(false, null, 'member_id is required', 422);
}
if ($schemeId <= 0) {
    json_response(false, null, 'scheme_id (Product) is required', 422);
}

$memberStmt = $pdo->prepare("SELECT MemberID, BranchID, CenterID, AssignedToStaffID FROM Member WHERE MemberID = ?");
$memberStmt->execute([$memberId]);
$member = $memberStmt->fetch();
if (!$member) {
    json_response(false, null, 'Member not found', 404);
}
assert_member_access($member, $claims);

// A deceased member (approved Death_Entry) can never get a new loan proposed.
$deathStmt = $pdo->prepare("SELECT 1 FROM Death_Entry WHERE MemberID = ? AND Status = 'Approved'");
$deathStmt->execute([$memberId]);
if ($deathStmt->fetchColumn()) {
    json_response(false, null, 'This member has an approved death record on file. A new loan cannot be proposed for a deceased member.', 409, 'MEMBER_DECEASED');
}

// A new loan proposal is only allowed once every existing loan of this
// member has reached a final stage (Closed/Rejected/Preclosed).
$openLoanStmt = $pdo->prepare("
  SELECT l.LoanNo FROM Loan l JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
  WHERE l.MemberID = ? AND stg.IsFinal = 0
");
$openLoanStmt->execute([$memberId]);
$openLoanNo = $openLoanStmt->fetchColumn();
if ($openLoanNo) {
    json_response(false, null, "This member already has an open loan ($openLoanNo). It must be closed, preclosed, or rejected before a new loan can be proposed.", 409);
}

$schemeStmt = $pdo->prepare("SELECT SchemeID, TenureMonths, DefaultLoanAmount, InterestRatePercent FROM Loan_Scheme WHERE SchemeID = ? AND IsActive = 1");
$schemeStmt->execute([$schemeId]);
$scheme = $schemeStmt->fetch();
if (!$scheme) {
    json_response(false, null, 'Invalid product', 422);
}

// A requested amount above the scheme default is only honored for a reloan,
// and only up to that member's server-computed eligible amount -- a fresh
// (first-time) borrower always gets exactly the scheme's default amount.
$loanAmount = (float)$scheme['DefaultLoanAmount'];
$requestedAmount = opt_num($body, 'amount');
if ($requestedAmount !== null) {
    $reloanEligibility = compute_reloan_eligibility($pdo, $memberId);
    if ($reloanEligibility === null) {
        json_response(false, null, 'A custom amount is only allowed for a reloan.', 422);
    }
    if ($requestedAmount <= 0 || $requestedAmount > $reloanEligibility['eligible_amount']) {
        json_response(false, null, 'Requested amount exceeds this member\'s pre-approved eligibility of ' . $reloanEligibility['eligible_amount'], 422);
    }
    $loanAmount = $requestedAmount;
}

$purposeId = (int)($body['purpose_id'] ?? 0) ?: null;
$appliedStageId = $pdo->query("SELECT StageID FROM Loan_Stage_Master WHERE StageName = 'Applied'")->fetchColumn();

$cycleStmt = $pdo->prepare("SELECT COUNT(*) FROM Loan WHERE MemberID = ?");
$cycleStmt->execute([$memberId]);
$loanCycle = (int)$cycleStmt->fetchColumn() + 1;

$pdo->beginTransaction();
try {
    $loanId = insert_and_get_id($pdo, "INSERT INTO Loan
        (MemberID, BranchID, CenterID, PurposeID, SchemeID, LoanAmountApplied, InterestRatePercent,
         TenureMonths, EMIAmount, ApplicationDate, LoanOfficerStaffID, CurrentStageID, LoanCycle, CreatedBy)
        VALUES (?,?,?,?,?,?,?,?,?, CAST(GETDATE() AS DATE), ?,?,?,?)", [
        $memberId,
        $member['BranchID'],
        $member['CenterID'],
        $purposeId,
        $schemeId,
        $loanAmount,
        $scheme['InterestRatePercent'],
        $scheme['TenureMonths'],
        opt_num($body, 'emi_amount'),
        (int)$claims['StaffID'],
        $appliedStageId,
        $loanCycle,
        (int)$claims['StaffID'],
    ], 'LoanID');

    $selfIncome = opt_num($body, 'self_income');
    $husbandSonIncome = opt_num($body, 'husband_son_income');
    $otherIncome = opt_num($body, 'other_income');
    $residentIncome = opt_num($body, 'resident_income');
    $houseExpenses = opt_num($body, 'house_expenses');
    $mfiExpenses = opt_num($body, 'mfi_expenses');
    $businessExpenses = opt_num($body, 'business_expenses');

    $incomeParts = array_filter([$selfIncome, $husbandSonIncome, $otherIncome, $residentIncome], fn($v) => $v !== null);
    $totalIncome = $incomeParts ? array_sum($incomeParts) : null;
    $expenseParts = array_filter([$houseExpenses, $mfiExpenses, $businessExpenses], fn($v) => $v !== null);
    $totalExpenses = $expenseParts ? array_sum($expenseParts) : null;
    $disposableIncome = ($totalIncome !== null || $totalExpenses !== null) ? ($totalIncome ?? 0) - ($totalExpenses ?? 0) : null;

    $assessmentId = insert_and_get_id($pdo, "INSERT INTO Loan_Income_Assessment
        (LoanID, SelfIncomeMonthly, HusbandSonIncomeMonthly, OtherIncomeMonthly, ResidentIncomeMonthly, TotalIncomeMonthly,
         HouseExpensesMonthly, MFIExpensesMonthly, BusinessExpensesMonthly, TotalExpensesMonthly,
         DisposableIncomeMonthly, SourceOfIncome, AssessmentDate)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?, CAST(GETDATE() AS DATE))", [
        $loanId,
        $selfIncome,
        $husbandSonIncome,
        $otherIncome,
        $residentIncome,
        $totalIncome,
        $houseExpenses,
        $mfiExpenses,
        $businessExpenses,
        $totalExpenses,
        $disposableIncome,
        $body['source_of_income'] ?? null,
    ], 'AssessmentID');

    // Every new proposal also opens a Branch-Manager sign-off request. This
    // is advisory (see Loan_Approval) -- it runs alongside, not instead of,
    // the existing GRT-pass auto-advance below, so a rejected proposal does
    // not by itself block GRT/disbursement in this first version.
    insert_and_get_id($pdo, "INSERT INTO Loan_Approval
        (LoanID, RequestType, Status, RequestedByStaffID)
        VALUES (?, 'Proposal', 'Pending', ?)", [
        $loanId,
        (int)$claims['StaffID'],
    ], 'LoanApprovalID');

    $ifscCode = trim((string)($body['ifsc_code'] ?? '')) ?: null;
    $bankName = trim((string)($body['bank_name'] ?? '')) ?: null;
    $bankBranch = trim((string)($body['bank_branch'] ?? '')) ?: null;
    $bankAddress = trim((string)($body['bank_address'] ?? '')) ?: null;
    $accountNumber = trim((string)($body['account_number'] ?? '')) ?: null;
    $beneficiaryName = trim((string)($body['beneficiary_name'] ?? '')) ?: null;

    if ($ifscCode !== null || $bankName !== null || $accountNumber !== null || $beneficiaryName !== null) {
        $existingBankStmt = $pdo->prepare("SELECT BankDetailID FROM Member_Bank_Details WHERE MemberID = ?");
        $existingBankStmt->execute([$memberId]);
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
            ")->execute([$memberId, ...$bankParams, (int)$claims['StaffID']]);
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('loans/create.php failed: ' . $e->getMessage());
    json_response(false, null, 'Failed to save loan proposal', 500);
}

json_response(true, [
    'loan_id' => $loanId,
    'assessment_id' => $assessmentId,
], 'Loan proposal saved');

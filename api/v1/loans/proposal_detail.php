<?php
/**
 * GET /api/v1/loans/proposal_detail.php?loan_id=
 *
 * Full editable detail for a pending loan Proposal, used by the mobile
 * "Review Loan Proposal" screen a reviewer (Branch Manager / Area Manager)
 * opens from their approval queue -- unlike loans/detail.php (read-only,
 * built for an already-active loan's repayment view), this returns the raw
 * editable inputs: scheme_id/purpose_id (not just names), the household
 * income assessment, and bank details -- everything proposal_update.php can
 * then save changes back to before approve/reject.
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';
require_once __DIR__ . '/../../../includes/permission_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();

if (!role_has_permission($pdo, $claims['RoleID'] ?? null, 'approve_loan_proposal')) {
    json_response(false, null, 'You do not have permission to review this proposal', 403);
}

$loanId = (int)($_GET['loan_id'] ?? 0);
if ($loanId <= 0) {
    json_response(false, null, 'loan_id is required', 422);
}

$stmt = $pdo->prepare("
  SELECT l.LoanID, l.LoanNo, l.MemberID, l.BranchID, l.CenterID, l.SchemeID, l.PurposeID,
         l.LoanAmountApplied, l.LoanAmountSanctioned, l.EMIAmount, l.ApplicationDate,
         m.FirstName, m.LastName, m.EnrollmentNo,
         b.BranchName, c.CenterName,
         ia.SelfIncomeMonthly, ia.HusbandSonIncomeMonthly, ia.OtherIncomeMonthly, ia.ResidentIncomeMonthly,
         ia.HouseExpensesMonthly, ia.MFIExpensesMonthly, ia.BusinessExpensesMonthly, ia.SourceOfIncome,
         bd.BankName, bd.IFSCCode, bd.BankBranch, bd.BankAddress, bd.AccountNumber, bd.BeneficiaryName
  FROM Loan l
  JOIN Member m ON l.MemberID = m.MemberID
  LEFT JOIN Branch b ON l.BranchID = b.BranchID
  LEFT JOIN Center c ON l.CenterID = c.CenterID
  LEFT JOIN Loan_Income_Assessment ia ON ia.LoanID = l.LoanID
  LEFT JOIN Member_Bank_Details bd ON bd.MemberID = l.MemberID
  WHERE l.LoanID = ?
");
$stmt->execute([$loanId]);
$l = $stmt->fetch();
if (!$l) {
    json_response(false, null, 'Loan not found', 404);
}
assert_loan_access($l, $claims);

$coBorrowerStmt = $pdo->prepare("SELECT TOP 1 FirstName, MiddleName, LastName FROM Co_Borrower WHERE MemberID = ? ORDER BY CoBorrowerID DESC");
$coBorrowerStmt->execute([$l['MemberID']]);
$cb = $coBorrowerStmt->fetch();
$coBorrowerName = $cb ? trim($cb['FirstName'] . ' ' . ($cb['MiddleName'] ? $cb['MiddleName'] . ' ' : '') . $cb['LastName']) : null;

$laStmt = $pdo->prepare("
  SELECT TOP 1 LoanApprovalID, Status FROM Loan_Approval
  WHERE LoanID = ? AND RequestType = 'Proposal' ORDER BY LoanApprovalID DESC
");
$laStmt->execute([$loanId]);
$la = $laStmt->fetch();

$grtStmt = $pdo->prepare("
  SELECT TOP 1 gsm.GRTStatusName, gd.GRTDate FROM GRT_Details gd
  JOIN GRT_Status_Master gsm ON gd.GRTStatusID = gsm.GRTStatusID
  WHERE gd.LoanID = ? ORDER BY gd.GRTID DESC
");
$grtStmt->execute([$loanId]);
$grt = $grtStmt->fetch();

json_response(true, [
    'loan_id'          => (int)$l['LoanID'],
    'loan_no'          => $l['LoanNo'],
    'loan_approval_id' => $la ? (int)$la['LoanApprovalID'] : null,
    'approval_status'  => $la['Status'] ?? null,
    'branch_name'      => $l['BranchName'],
    'center_name'      => $l['CenterName'],
    'enrollment_no'    => $l['EnrollmentNo'],
    'borrower_name'    => trim($l['FirstName'] . ' ' . $l['LastName']),
    'co_borrower_name' => $coBorrowerName,
    'scheme_id'        => $l['SchemeID'] !== null ? (int)$l['SchemeID'] : null,
    'loan_amount'      => to_money($l['LoanAmountSanctioned'] ?: $l['LoanAmountApplied']),
    'purpose_id'       => $l['PurposeID'] !== null ? (int)$l['PurposeID'] : null,
    'emi_amount'       => to_money($l['EMIAmount']),
    'self_income'          => $l['SelfIncomeMonthly'] !== null ? to_money($l['SelfIncomeMonthly']) : null,
    'husband_son_income'   => $l['HusbandSonIncomeMonthly'] !== null ? to_money($l['HusbandSonIncomeMonthly']) : null,
    'other_income'         => $l['OtherIncomeMonthly'] !== null ? to_money($l['OtherIncomeMonthly']) : null,
    'resident_income'      => $l['ResidentIncomeMonthly'] !== null ? to_money($l['ResidentIncomeMonthly']) : null,
    'house_expenses'       => $l['HouseExpensesMonthly'] !== null ? to_money($l['HouseExpensesMonthly']) : null,
    'mfi_expenses'         => $l['MFIExpensesMonthly'] !== null ? to_money($l['MFIExpensesMonthly']) : null,
    'business_expenses'    => $l['BusinessExpensesMonthly'] !== null ? to_money($l['BusinessExpensesMonthly']) : null,
    'source_of_income'     => $l['SourceOfIncome'],
    'ifsc_code'         => $l['IFSCCode'],
    'bank_name'         => $l['BankName'],
    'bank_branch'       => $l['BankBranch'],
    'bank_address'      => $l['BankAddress'],
    'account_number'    => $l['AccountNumber'],
    'beneficiary_name'  => $l['BeneficiaryName'],
    'application_date'  => to_iso_date($l['ApplicationDate']),
    'grt_status'        => $grt ? ($grt['GRTStatusName'] === 'P' ? 'Pass' : 'Fail') : null,
    'grt_date'          => $grt ? to_iso_date($grt['GRTDate']) : null,
]);

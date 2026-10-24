<?php
/**
 * POST /api/v1/reports/dcr_closing_create.php
 * Body: {
 *   date,
 *   fund_transfer_from_another_branch, rent_expenses, stationary_assets,
 *   salary_payment, other_expenses, petty_cash_payment,
 *   cash_deposit_by_ho_bank, cash_deposit_by_branch_bank,
 *   enrollment_fee, risk_fee, processing_fee,
 *   other_income, bank_withdrawal, fund_received, fund_received_by_ho
 * } (every field optional, defaults to 0)
 *
 * Submits one Day Closing Report row for the caller's branch + date. The
 * auto fields (loan disbursement, opening balance, collection dues/
 * collected, preclosure, arrear recovery) are recomputed here from scratch
 * -- exactly the same queries as dcr_closing_prefill.php -- never trusted
 * from the client, so a stale prefill can't be submitted as if it were
 * current. One submission per branch per day; resubmitting the same day
 * is rejected (see UQ_DayClosingReport_Branch_Date).
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();
$branchId = $claims['BranchID'] !== null ? (int)$claims['BranchID'] : null;
if (!$branchId) {
    json_response(false, null, 'No branch assigned to your account', 422);
}

$body = get_json_body();
$date = trim((string)($body['date'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    json_response(false, null, 'date must be YYYY-MM-DD', 422);
}

$existing = $pdo->prepare("SELECT 1 FROM Day_Closing_Report WHERE BranchID = ? AND ClosingDate = ?");
$existing->execute([$branchId, $date]);
if ($existing->fetchColumn()) {
    json_response(false, null, 'A Day Closing Report has already been submitted for this date', 409);
}

function dcrField(array $body, string $key): float {
    return (float)($body[$key] ?? 0);
}

$fundTransfer = dcrField($body, 'fund_transfer_from_another_branch');
$rentExpenses = dcrField($body, 'rent_expenses');
$stationaryAssets = dcrField($body, 'stationary_assets');
$salaryPayment = dcrField($body, 'salary_payment');
$otherExpenses = dcrField($body, 'other_expenses');
$pettyCashPayment = dcrField($body, 'petty_cash_payment');
$cashDepositByHoBank = dcrField($body, 'cash_deposit_by_ho_bank');
$cashDepositByBranchBank = dcrField($body, 'cash_deposit_by_branch_bank');
$enrollmentFee = dcrField($body, 'enrollment_fee');
$riskFee = dcrField($body, 'risk_fee');
$processingFee = dcrField($body, 'processing_fee');
$otherIncome = dcrField($body, 'other_income');
$bankWithdrawal = dcrField($body, 'bank_withdrawal');
$fundReceived = dcrField($body, 'fund_received');
$fundReceivedByHo = dcrField($body, 'fund_received_by_ho');

// -- Recompute every auto field server-side (identical to dcr_closing_prefill.php). --
$disbStmt = $pdo->prepare("
  SELECT COALESCE(SUM(COALESCE(LoanAmountSanctioned, LoanAmountApplied)),0)
  FROM Loan WHERE BranchID = ? AND DisbursementDate = ?
");
$disbStmt->execute([$branchId, $date]);
$loanDisbursement = (float)$disbStmt->fetchColumn();

$openingStmt = $pdo->prepare("
  SELECT TOP 1 GrandTotal FROM Day_Closing_Report
  WHERE BranchID = ? AND ClosingDate < ? ORDER BY ClosingDate DESC
");
$openingStmt->execute([$branchId, $date]);
$openingBalanceRaw = $openingStmt->fetchColumn();
$openingBalance = $openingBalanceRaw === false ? 0.0 : (float)$openingBalanceRaw;

$collectionStmt = $pdo->prepare("
  SELECT COALESCE(SUM(dcs.EMIAmount),0) AS DueAmount,
         COALESCE(SUM(CASE WHEN dcs.CollectionStatus = 'Collected' THEN dcs.CollectionAmount ELSE 0 END),0) AS CollectedAmount
  FROM Daily_Collection_Sheet dcs
  WHERE dcs.BranchID = ? AND dcs.CollectionDueDate = ?
");
$collectionStmt->execute([$branchId, $date]);
$collectionRow = $collectionStmt->fetch();
$collectionDues = (float)$collectionRow['DueAmount'];
$collectedAmount = (float)$collectionRow['CollectedAmount'];

$precloseStmt = $pdo->prepare("
  SELECT COALESCE(SUM(la.PrecloseQuoteAmount),0)
  FROM Loan_Approval la JOIN Loan l ON la.LoanID = l.LoanID
  WHERE l.BranchID = ? AND la.RequestType = 'Preclose' AND la.Status = 'Approved'
    AND CAST(la.ReviewedAt AS DATE) = ?
");
$precloseStmt->execute([$branchId, $date]);
$preclosureAmount = (float)$precloseStmt->fetchColumn();

$arrearStmt = $pdo->prepare("
  SELECT COALESCE(SUM(c.TotalCollected),0)
  FROM Collection c
  JOIN Repayment_Schedule rs ON c.ScheduleID = rs.ScheduleID
  JOIN Loan l ON c.LoanID = l.LoanID
  WHERE l.BranchID = ? AND c.CollectionDate = ? AND c.CollectionDate > rs.DueDate
");
$arrearStmt->execute([$branchId, $date]);
$arrearRecoveryAmount = (float)$arrearStmt->fetchColumn();

$totalOutflow = $loanDisbursement + $fundTransfer + $rentExpenses + $stationaryAssets + $salaryPayment
    + $otherExpenses + $pettyCashPayment + $cashDepositByHoBank + $cashDepositByBranchBank;

// CollectionDues is reference-only (what was due, not what was banked) --
// deliberately excluded from the Total Inflow sum.
$totalInflow = $openingBalance + $collectedAmount + $enrollmentFee + $riskFee + $processingFee
    + $preclosureAmount + $arrearRecoveryAmount + $otherIncome + $bankWithdrawal + $fundReceived + $fundReceivedByHo;

$grandTotal = $totalInflow - $totalOutflow;

try {
    $newId = insert_and_get_id($pdo, "INSERT INTO Day_Closing_Report (
        BranchID, ClosingDate,
        LoanDisbursement, FundTransferFromAnotherBranch, RentExpenses, StationaryAssets, SalaryPayment,
        OtherExpenses, PettyCashPayment, CashDepositByHOBank, CashDepositByBranchBank, TotalOutflow,
        OpeningBalance, CollectionDues, CollectedAmount, EnrollmentFee, RiskFee, ProcessingFee,
        PreclosureAmount, ArrearRecoveryAmount, OtherIncome, BankWithdrawal, FundReceived, FundReceivedByHO, TotalInflow,
        GrandTotal, CreatedByStaffID
    ) VALUES (?,?, ?,?,?,?,?, ?,?,?,?,?, ?,?,?,?,?,?, ?,?,?,?,?,?,?, ?,?)", [
        $branchId, $date,
        $loanDisbursement, $fundTransfer, $rentExpenses, $stationaryAssets, $salaryPayment,
        $otherExpenses, $pettyCashPayment, $cashDepositByHoBank, $cashDepositByBranchBank, $totalOutflow,
        $openingBalance, $collectionDues, $collectedAmount, $enrollmentFee, $riskFee, $processingFee,
        $preclosureAmount, $arrearRecoveryAmount, $otherIncome, $bankWithdrawal, $fundReceived, $fundReceivedByHo, $totalInflow,
        $grandTotal, (int)$claims['StaffID'],
    ], 'DayClosingID');
} catch (Throwable $e) {
    error_log('dcr_closing_create.php failed: ' . $e->getMessage());
    json_response(false, null, 'Failed to submit Day Closing Report', 500);
}

json_response(true, [
    'day_closing_id' => $newId,
    'total_outflow'  => to_money($totalOutflow),
    'total_inflow'   => to_money($totalInflow),
    'grand_total'    => to_money($grandTotal),
], 'Day Closing Report submitted');

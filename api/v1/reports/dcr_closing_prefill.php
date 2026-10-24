<?php
/**
 * GET /api/v1/reports/dcr_closing_prefill.php?date=YYYY-MM-DD (default today)
 *
 * The auto-computed figures for the Day Closing Report form, for the
 * caller's own branch -- loan disbursement, opening balance (carried from
 * the previous day's GrandTotal), collection dues/collected, preclosure
 * payoff, and arrear recovery. These are recomputed fresh again at submit
 * time by dcr_closing_create.php -- this endpoint only prefills the form,
 * it never writes anything.
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();
$branchId = $claims['BranchID'] !== null ? (int)$claims['BranchID'] : null;
if (!$branchId) {
    json_response(false, null, 'No branch assigned to your account', 422);
}

$date = trim((string)($_GET['date'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    json_response(false, null, 'date must be YYYY-MM-DD', 422);
}

$alreadySubmittedStmt = $pdo->prepare("SELECT 1 FROM Day_Closing_Report WHERE BranchID = ? AND ClosingDate = ?");
$alreadySubmittedStmt->execute([$branchId, $date]);
$alreadySubmitted = (bool)$alreadySubmittedStmt->fetchColumn();

// Loan disbursement -- every loan disbursed today for this branch.
$disbStmt = $pdo->prepare("
  SELECT COALESCE(SUM(COALESCE(LoanAmountSanctioned, LoanAmountApplied)),0)
  FROM Loan WHERE BranchID = ? AND DisbursementDate = ?
");
$disbStmt->execute([$branchId, $date]);
$loanDisbursement = $disbStmt->fetchColumn();

// Opening balance -- the previous day's GrandTotal for this branch, or 0
// if nothing was ever closed before (first-ever submission).
$openingStmt = $pdo->prepare("
  SELECT TOP 1 GrandTotal FROM Day_Closing_Report
  WHERE BranchID = ? AND ClosingDate < ? ORDER BY ClosingDate DESC
");
$openingStmt->execute([$branchId, $date]);
$openingBalance = $openingStmt->fetchColumn();
if ($openingBalance === false) {
    $openingBalance = 0;
}

// Collection dues/collected -- same definition as dcr.php's own report.
$collectionStmt = $pdo->prepare("
  SELECT COALESCE(SUM(dcs.EMIAmount),0) AS DueAmount,
         COALESCE(SUM(CASE WHEN dcs.CollectionStatus = 'Collected' THEN dcs.CollectionAmount ELSE 0 END),0) AS CollectedAmount
  FROM Daily_Collection_Sheet dcs
  WHERE dcs.BranchID = ? AND dcs.CollectionDueDate = ?
");
$collectionStmt->execute([$branchId, $date]);
$collectionRow = $collectionStmt->fetch();

// Preclosure amount -- same source as the Today Preclose report.
$precloseStmt = $pdo->prepare("
  SELECT COALESCE(SUM(la.PrecloseQuoteAmount),0)
  FROM Loan_Approval la JOIN Loan l ON la.LoanID = l.LoanID
  WHERE l.BranchID = ? AND la.RequestType = 'Preclose' AND la.Status = 'Approved'
    AND CAST(la.ReviewedAt AS DATE) = ?
");
$precloseStmt->execute([$branchId, $date]);
$preclosureAmount = $precloseStmt->fetchColumn();

// Arrear recovery -- same source as the Arrear Collection report, today only.
$arrearStmt = $pdo->prepare("
  SELECT COALESCE(SUM(c.TotalCollected),0)
  FROM Collection c
  JOIN Repayment_Schedule rs ON c.ScheduleID = rs.ScheduleID
  JOIN Loan l ON c.LoanID = l.LoanID
  WHERE l.BranchID = ? AND c.CollectionDate = ? AND c.CollectionDate > rs.DueDate
");
$arrearStmt->execute([$branchId, $date]);
$arrearRecoveryAmount = $arrearStmt->fetchColumn();

json_response(true, [
    'date'                    => $date,
    'already_submitted'       => $alreadySubmitted,
    'loan_disbursement'       => to_money($loanDisbursement),
    'opening_balance'         => to_money($openingBalance),
    'collection_dues'         => to_money($collectionRow['DueAmount']),
    'collected_amount'        => to_money($collectionRow['CollectedAmount']),
    'preclosure_amount'       => to_money($preclosureAmount),
    'arrear_recovery_amount'  => to_money($arrearRecoveryAmount),
]);

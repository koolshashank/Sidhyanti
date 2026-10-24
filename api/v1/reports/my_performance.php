<?php
/**
 * GET /api/v1/reports/my_performance.php
 *
 * A personal (not branch/officer-comparison) performance summary for the
 * calling staff member: leads sourced this month, loans disbursed this
 * month, amount collected this month, and their current total arrears --
 * deliberately scoped to "my own numbers", not the web CRM's full
 * management-level reports.php dashboard (officer comparison, branch
 * rollups, arrears aging buckets etc. are back-office views out of scope
 * for the mobile CM persona).
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();
$staffId = (int)$claims['StaffID'];

$leadsStmt = $pdo->prepare("
  SELECT COUNT(*) FROM Member
  WHERE AssignedToStaffID = ? AND CreatedAt >= DATEADD(DAY, 1 - DAY(GETDATE()), CAST(GETDATE() AS DATE))
");
$leadsStmt->execute([$staffId]);
$leadsSourced = (int)$leadsStmt->fetchColumn();

$disbursedStmt = $pdo->prepare("
  SELECT COUNT(*), COALESCE(SUM(COALESCE(LoanAmountSanctioned, LoanAmountApplied)),0)
  FROM Loan
  WHERE LoanOfficerStaffID = ? AND DisbursementDate >= DATEADD(DAY, 1 - DAY(GETDATE()), CAST(GETDATE() AS DATE))
");
$disbursedStmt->execute([$staffId]);
[$loansDisbursedCount, $amountDisbursed] = $disbursedStmt->fetch(PDO::FETCH_NUM);

$collectedStmt = $pdo->prepare("
  SELECT COALESCE(SUM(TotalCollected),0) FROM Collection
  WHERE CollectedByStaffID = ? AND CollectionDate >= DATEADD(DAY, 1 - DAY(GETDATE()), CAST(GETDATE() AS DATE))
");
$collectedStmt->execute([$staffId]);
$collectedThisMonth = $collectedStmt->fetchColumn();

// Collection has no separate settable target -- it's always "100% of
// what's due" -- so this is just the month's total due, to show progress
// against on the mobile Target vs Achievement tracker.
$collectionDueStmt = $pdo->prepare("
  SELECT COALESCE(SUM(EMIAmount),0) FROM Daily_Collection_Sheet
  WHERE OfficerStaffID = ? AND CollectionDueDate >= DATEADD(DAY, 1 - DAY(GETDATE()), CAST(GETDATE() AS DATE))
    AND CollectionDueDate < DATEADD(MONTH, 1, DATEADD(DAY, 1 - DAY(GETDATE()), CAST(GETDATE() AS DATE)))
");
$collectionDueStmt->execute([$staffId]);
$collectionDueThisMonth = $collectionDueStmt->fetchColumn();

// Disbursement target -- set by Admin/Branch Manager via staff_targets.php
// (web), for the current calendar month.
$targetStmt = $pdo->prepare("
  SELECT DisbursementTarget FROM Staff_Target WHERE StaffID = ? AND TargetYear = ? AND TargetMonth = ?
");
$targetStmt->execute([$staffId, (int)date('Y'), (int)date('n')]);
$disbursementTarget = $targetStmt->fetchColumn();

[$loanScopeSql, $loanScopeParams] = loan_scope_where($claims, 'l');
$arrearStmt = $pdo->prepare("
  ;WITH UnpaidAgg AS (
    SELECT rs.LoanID, MIN(rs.DueDate) AS OldestUnpaidDue, SUM(rs.PrincipalDue) AS OverdueAmount
    FROM Repayment_Schedule rs WHERE rs.IsPaid = 0 GROUP BY rs.LoanID
  )
  SELECT COUNT(DISTINCT ua.LoanID), COALESCE(SUM(ua.OverdueAmount),0)
  FROM UnpaidAgg ua JOIN Loan l ON ua.LoanID = l.LoanID
  WHERE ua.OldestUnpaidDue < CAST(GETDATE() AS DATE)" . ($loanScopeSql !== '' ? " AND $loanScopeSql" : "")
);
$arrearStmt->execute($loanScopeParams);
[$arrearLoanCount, $arrearAmount] = $arrearStmt->fetch(PDO::FETCH_NUM);

// ALC (Active Loan Clients) -- every loan currently in the Active stage,
// same "Active" definition used by staff.php's own active-loan count.
$alcStmt = $pdo->prepare("
  SELECT COUNT(*) FROM Loan l JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
  WHERE stg.StageName = 'Active'" . ($loanScopeSql !== '' ? " AND $loanScopeSql" : "")
);
$alcStmt->execute($loanScopeParams);
$activeLoanClients = (int)$alcStmt->fetchColumn();

// POS Till Date (Portfolio Outstanding) -- total unpaid principal across
// every Active loan right now, not month-scoped like the metrics above.
// Same definition as dashboard.php's own POS snapshot.
$posStmt = $pdo->prepare("
  SELECT COALESCE(SUM(rs.PrincipalDue),0) FROM Repayment_Schedule rs
  JOIN Loan l ON rs.LoanID = l.LoanID JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
  WHERE stg.StageName = 'Active' AND rs.IsPaid = 0" . ($loanScopeSql !== '' ? " AND $loanScopeSql" : "")
);
$posStmt->execute($loanScopeParams);
$posTillDate = $posStmt->fetchColumn();

json_response(true, [
    'leads_sourced_this_month'    => $leadsSourced,
    'loans_disbursed_this_month'  => (int)$loansDisbursedCount,
    'amount_disbursed_this_month' => to_money($amountDisbursed),
    'collected_this_month'        => to_money($collectedThisMonth),
    'arrear_loan_count'           => (int)$arrearLoanCount,
    'arrear_amount'               => to_money($arrearAmount),
    'active_loan_clients'         => $activeLoanClients,
    'pos_till_date'               => to_money($posTillDate),
    'disbursement_target'         => to_money($disbursementTarget !== false ? $disbursementTarget : 0),
    'collection_due_this_month'   => to_money($collectionDueThisMonth),
]);

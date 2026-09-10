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

json_response(true, [
    'leads_sourced_this_month'    => $leadsSourced,
    'loans_disbursed_this_month'  => (int)$loansDisbursedCount,
    'amount_disbursed_this_month' => to_money($amountDisbursed),
    'collected_this_month'        => to_money($collectedThisMonth),
    'arrear_loan_count'           => (int)$arrearLoanCount,
    'arrear_amount'               => to_money($arrearAmount),
]);

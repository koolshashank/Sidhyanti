<?php
/**
 * Shared by loan_view.php's generic Disbursed/Active stage-change handler
 * (web CRM) and api/v1/loans/disburse.php (mobile), so the amortization
 * math only lives in one place. No session/auth dependency -- safe to
 * include from either the session-based web pages or the stateless API.
 */

/**
 * Generates a loan's Repayment_Schedule (flat-EMI, monthly) if none exists
 * yet, and stamps DisbursementDate/EMIAmount on the Loan itself -- a no-op
 * if a schedule already exists. Returns the number of installment rows
 * created (0 if it was already generated).
 */
function generate_repayment_schedule($pdo, $loanId) {
    $existing = $pdo->prepare("SELECT COUNT(*) FROM Repayment_Schedule WHERE LoanID=?");
    $existing->execute([$loanId]);
    if ($existing->fetchColumn() > 0) {
        return 0;
    }

    $loan = $pdo->prepare("SELECT * FROM Loan WHERE LoanID=?");
    $loan->execute([$loanId]);
    $loan = $loan->fetch();
    if (!$loan) {
        return 0;
    }

    $amount = $loan['LoanAmountSanctioned'] ?: $loan['LoanAmountApplied'];
    $tenure = $loan['TenureMonths'];
    $rate = $loan['InterestRatePercent'];
    $principalPerInst = round($amount / $tenure, 2);
    $interestPerInst = round(($amount * $rate / 100) / 12, 2);
    $emi = $principalPerInst + $interestPerInst;

    $pdo->prepare("UPDATE Loan SET DisbursementDate = COALESCE(DisbursementDate, CAST(GETDATE() AS DATE)), EMIAmount = ? WHERE LoanID = ?")
        ->execute([$emi, $loanId]);

    $ins = $pdo->prepare("INSERT INTO Repayment_Schedule (LoanID, InstallmentNo, DueDate, PrincipalDue, InterestDue, EMIAmount) VALUES (?,?,?,?,?,?)");
    for ($i = 1; $i <= $tenure; $i++) {
        $due = date('Y-m-d', strtotime("+$i months"));
        $ins->execute([$loanId, $i, $due, $principalPerInst, $interestPerInst, $emi]);
    }
    return $tenure;
}

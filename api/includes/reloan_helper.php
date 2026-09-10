<?php
/**
 * Computes reloan eligibility for a member -- an amount increase over their
 * most recently completed (Closed/Preclosed) loan, based on admin-configured
 * Reloan_Eligibility_Rule rows. Deliberately member-history-driven rather
 * than scheme-driven, and only ever applies to reloans: a member with no
 * completed loan (a fresh/first-time borrower) always gets null here.
 *
 * Criteria combined per rule: how many loan cycles this member has
 * completed (MinCycleNumber) and how punctually the most recent one was
 * repaid (MaxLatePayments, counting installments paid after their due date
 * or never paid). Among all rules a member qualifies for, the one with the
 * highest IncreasePercent wins.
 */
function compute_reloan_eligibility(PDO $pdo, int $memberId): ?array {
    $prevLoan = $pdo->prepare("
        SELECT TOP 1 LoanID, LoanNo, LoanAmountSanctioned, LoanAmountApplied
        FROM Loan l JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
        WHERE l.MemberID = ? AND stg.StageName IN ('Closed', 'Preclosed')
        ORDER BY COALESCE(l.ClosureDate, CAST(l.UpdatedAt AS DATE)) DESC, l.LoanID DESC
    ");
    $prevLoan->execute([$memberId]);
    $prevLoan = $prevLoan->fetch();
    if (!$prevLoan) {
        return null;
    }

    $cycleCount = $pdo->prepare("
        SELECT COUNT(*) FROM Loan l JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
        WHERE l.MemberID = ? AND stg.StageName IN ('Closed', 'Preclosed')
    ");
    $cycleCount->execute([$memberId]);
    $cycleNumber = (int)$cycleCount->fetchColumn() + 1;

    $lateStmt = $pdo->prepare("
        SELECT COUNT(*) FROM Repayment_Schedule rs
        LEFT JOIN Collection c ON c.ScheduleID = rs.ScheduleID
        WHERE rs.LoanID = ? AND (
            (c.CollectionDate IS NOT NULL AND c.CollectionDate > rs.DueDate)
            OR (c.CollectionDate IS NULL AND rs.IsPaid = 0)
        )
    ");
    $lateStmt->execute([$prevLoan['LoanID']]);
    $latePayments = (int)$lateStmt->fetchColumn();

    $ruleStmt = $pdo->prepare("
        SELECT TOP 1 RuleID, RuleName, IncreasePercent FROM Reloan_Eligibility_Rule
        WHERE IsActive = 1 AND MinCycleNumber <= ? AND MaxLatePayments >= ?
        ORDER BY IncreasePercent DESC
    ");
    $ruleStmt->execute([$cycleNumber, $latePayments]);
    $rule = $ruleStmt->fetch();
    if (!$rule) {
        return null;
    }

    $previousAmount = (float)($prevLoan['LoanAmountSanctioned'] ?: $prevLoan['LoanAmountApplied']);
    $increasePercent = (float)$rule['IncreasePercent'];
    $eligibleAmount = round($previousAmount * (1 + $increasePercent / 100) / 100) * 100;

    return [
        'previous_loan_no'     => $prevLoan['LoanNo'],
        'previous_loan_amount' => $previousAmount,
        'cycle_number'         => $cycleNumber,
        'late_payments'        => $latePayments,
        'rule_name'            => $rule['RuleName'],
        'increase_percent'     => $increasePercent,
        'eligible_amount'      => $eligibleAmount,
    ];
}

<?php
/**
 * Computes the "what happens if this loan is preclosed today" figures --
 * shared by the FO's Request Preclose screen and the BM's Preclose
 * Approval review so both sides see the same numbers. No foreclosure
 * charge exists in the schema, so the preclose amount is simply the
 * outstanding principal; the remaining (not-yet-due) interest across the
 * rest of the schedule is what the member saves by closing early.
 *
 * Returns null if the loan has no repayment schedule yet (shouldn't
 * normally happen for a Disbursed/Active loan, but guards against it).
 */
function loan_preclose_quote(PDO $pdo, int $loanId): ?array {
    $stmt = $pdo->prepare("
      SELECT PrincipalDue, InterestDue, EMIAmount, IsPaid,
        (SELECT MAX(c.CollectionDate) FROM Collection c WHERE c.ScheduleID = rs.ScheduleID) AS LedgerCollectionDate,
        (SELECT TOP 1 dcs.CollectionDate FROM Daily_Collection_Sheet dcs WHERE dcs.ScheduleID = rs.ScheduleID AND dcs.CollectionStatus IN ('Collected','Preclosed')) AS SheetCollectionDate
      FROM Repayment_Schedule rs WHERE rs.LoanID = ?
    ");
    $stmt->execute([$loanId]);
    $rows = $stmt->fetchAll();
    if (!$rows) {
        return null;
    }

    $paidCount = 0;
    $principalOutstanding = 0.0;
    $interestSavings = 0.0;
    foreach ($rows as $r) {
        $isPaid = to_bool($r['IsPaid']) || $r['LedgerCollectionDate'] || $r['SheetCollectionDate'];
        if ($isPaid) {
            $paidCount++;
            continue;
        }
        $principalOutstanding += to_money($r['PrincipalDue']);
        $interestSavings += to_money($r['InterestDue']);
    }

    return [
        'installments_paid'  => $paidCount,
        'total_installments' => count($rows),
        'principal_outstanding' => round($principalOutstanding, 2),
        'interest_savings'      => round($interestSavings, 2),
        'preclose_amount'       => round($principalOutstanding, 2),
    ];
}

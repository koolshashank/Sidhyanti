<?php
/**
 * Computes a loan's current arrear picture -- shared by the BM's Partial
 * Payment approval queue (mobile + web) so a reviewer sees the full
 * context (not just the one installment being part-paid) before
 * approving/rejecting: total overdue amount, how many installments are
 * broken, and which installment number it started breaking from.
 */
function loan_arrear_summary(PDO $pdo, int $loanId): array {
    $stmt = $pdo->prepare("
      SELECT rs.InstallmentNo, rs.DueDate, rs.EMIAmount, rs.IsPaid,
        (SELECT MAX(c.CollectionDate) FROM Collection c WHERE c.ScheduleID = rs.ScheduleID) AS LedgerCollectionDate,
        (SELECT TOP 1 dcs.CollectionDate FROM Daily_Collection_Sheet dcs WHERE dcs.ScheduleID = rs.ScheduleID AND dcs.CollectionStatus IN ('Collected','Preclosed')) AS SheetCollectionDate
      FROM Repayment_Schedule rs WHERE rs.LoanID = ?
      ORDER BY rs.InstallmentNo
    ");
    $stmt->execute([$loanId]);
    $rows = $stmt->fetchAll();

    $today = date('Y-m-d');
    $arrearAmount = 0.0;
    $overdueCount = 0;
    $firstOverdueInstallmentNo = null;

    foreach ($rows as $r) {
        $isPaid = to_bool($r['IsPaid']) || $r['LedgerCollectionDate'] || $r['SheetCollectionDate'];
        $dueDate = to_iso_date($r['DueDate']);
        if (!$isPaid && $dueDate !== null && $dueDate < $today) {
            $arrearAmount += to_money($r['EMIAmount']);
            $overdueCount++;
            if ($firstOverdueInstallmentNo === null) {
                $firstOverdueInstallmentNo = (int)$r['InstallmentNo'];
            }
        }
    }

    return [
        'arrear_amount' => round($arrearAmount, 2),
        'overdue_installment_count' => $overdueCount,
        'first_overdue_installment_no' => $firstOverdueInstallmentNo,
    ];
}

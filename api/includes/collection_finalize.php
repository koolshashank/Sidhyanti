<?php
/**
 * Finalizes a collected payment against Daily_Collection_Sheet/Collection.
 * Extracted verbatim from api/v1/collections/record.php so the full-payment
 * path (called immediately) and the partial-payment path (called only once
 * a Branch Manager approves it, from api/v1/collections/partial_payment_approve.php)
 * share the exact same logic and can never drift apart.
 *
 * Must be called inside an already-open PDO transaction. Throws ApiError on
 * a race (installment already recorded elsewhere).
 */

/** Allocates a collected amount against PrincipalDue/InterestDue, remainder as penalty. */
function allocate_payment(float $amount, float $principalDue, float $interestDue): array {
    $remaining = $amount;
    $principalCollected = min($remaining, $principalDue);
    $remaining -= $principalCollected;
    $interestCollected = min($remaining, $interestDue);
    $remaining -= $interestCollected;
    $penaltyCollected = $remaining;
    return [$principalCollected, $interestCollected, $penaltyCollected];
}

/** Returns true if the schedule is now fully paid (per the DB trigger's own re-derived IsPaid). */
function finalize_collection(
    PDO $pdo,
    int $loanId,
    int $scheduleId,
    float $amount,
    string $collectionDate,
    float $principalCollected,
    float $interestCollected,
    float $penaltyCollected,
    int $staffId,
    ?string $remarks,
    bool $isPartPayment
): bool {
    $dcsUpd = $pdo->prepare("
        UPDATE Daily_Collection_Sheet
        SET CollectionAmount = ?, CollectionDate = ?, CollectionStatus = 'Collected',
            IsPartPayment = ?, UpdatedAt = GETDATE()
        WHERE ScheduleID = ? AND CollectionStatus IS NULL
    ");
    $dcsUpd->execute([$amount, $collectionDate, $isPartPayment ? 1 : 0, $scheduleId]);
    if ($dcsUpd->rowCount() !== 1) {
        throw new ApiError(409, 'This installment was already recorded');
    }

    $pdo->prepare("
        INSERT INTO Collection (LoanID, ScheduleID, CollectionDate, PrincipalCollected, InterestCollected, PenaltyCollected, CollectedByStaffID, Remarks)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ")->execute([$loanId, $scheduleId, $collectionDate, $principalCollected, $interestCollected, $penaltyCollected, $staffId, $remarks !== null && $remarks !== '' ? $remarks : null]);

    // TR_Collection_UpdateSchedule (AFTER INSERT trigger on Collection) sums every
    // Collection row for this ScheduleID and sets Repayment_Schedule.IsPaid itself
    // once Principal+Interest collected meets PrincipalDue+InterestDue -- we must
    // not also set IsPaid here, or we'd race the trigger and misfire "already paid"
    // on a completely fresh payment. Re-read it to report the true resulting status.
    $isPaidStmt = $pdo->prepare("SELECT IsPaid FROM Repayment_Schedule WHERE ScheduleID = ?");
    $isPaidStmt->execute([$scheduleId]);
    return to_bool($isPaidStmt->fetchColumn());
}

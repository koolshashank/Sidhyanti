<?php
/**
 * Slab-based incentive calculation -- Admin configures ranges of monthly
 * target-achievement % to a flat incentive amount, separately for
 * Disbursement and Collection (see database/43_incentive_slabs.sql).
 * Computed live wherever it's needed (my_performance.php, incentive_report.
 * php) rather than stored, same convention as every other derived figure
 * in this app.
 */

/** Active slabs for one metric, ascending by MinAchievementPercent. */
function incentive_slabs(PDO $pdo, string $metricType): array {
    $stmt = $pdo->prepare("SELECT * FROM Incentive_Slab WHERE MetricType = ? AND IsActive = 1 ORDER BY MinAchievementPercent");
    $stmt->execute([$metricType]);
    return $stmt->fetchAll();
}

/** The flat amount for whichever slab [Min, Max) an achievement % falls into -- 0 if none match. */
function incentive_amount_for(array $slabs, float $achievementPercent): float {
    foreach ($slabs as $slab) {
        $min = (float)$slab['MinAchievementPercent'];
        $max = $slab['MaxAchievementPercent'] !== null ? (float)$slab['MaxAchievementPercent'] : null;
        if ($achievementPercent >= $min && ($max === null || $achievementPercent < $max)) {
            return (float)$slab['IncentiveAmount'];
        }
    }
    return 0.0;
}

<?php
/**
 * JSON-boundary normalizers for the mobile API. pdo_sqlsrv returns DECIMAL
 * columns as PHP strings (to preserve precision) and DATE/DATETIME columns
 * as driver-dependent strings — both need explicit normalization so
 * json_encode() emits a JSON number for money and a consistent ISO string
 * for dates, regardless of which of config/db.php's two drivers connected.
 */

function to_money($v): float {
    return $v === null ? 0.0 : (float)$v;
}

/** Normalizes a SQL Server DATE/DATETIME string to ISO 8601 ("Y-m-d" or "Y-m-d\TH:i:s"). Null-safe. */
function to_iso_date($v): ?string {
    if ($v === null || $v === '') {
        return null;
    }
    $hasTime = strpos((string)$v, ':') !== false;
    $dt = date_create((string)$v);
    if (!$dt) {
        return null;
    }
    return $dt->format($hasTime ? 'Y-m-d\TH:i:s' : 'Y-m-d');
}

function to_bool($v): bool {
    return !empty($v);
}

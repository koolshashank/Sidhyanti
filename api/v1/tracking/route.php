<?php
/**
 * GET /api/v1/tracking/route.php?staff_id={id}&date=YYYY-MM-DD
 * Admin only.
 *
 * Every point captured by that staff member on that calendar date, ordered
 * oldest-first, across however many duty sessions they had that day. Points
 * with accuracy worse than 100m are dropped as GPS noise before the route
 * and distance are built. Distance is computed with the Haversine formula
 * over consecutive (filtered) points.
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();
if (!is_admin_role($claims)) {
    json_response(false, null, 'Forbidden', 403);
}

$staffId = (int)($_GET['staff_id'] ?? 0);
$date = $_GET['date'] ?? '';
if ($staffId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    json_response(false, null, 'staff_id and date (YYYY-MM-DD) are required', 422);
}

$stmt = $pdo->prepare("
    SELECT Latitude, Longitude, Accuracy, Speed, CapturedAt
    FROM Duty_Session_Point
    WHERE StaffID = ?
      AND CAST(CapturedAt AS DATE) = ?
      AND (Accuracy IS NULL OR Accuracy <= 100)
    ORDER BY CapturedAt ASC
");
$stmt->execute([$staffId, $date]);
$rows = $stmt->fetchAll();

/** Great-circle distance in metres between two lat/lng points. */
function haversine_metres(float $lat1, float $lng1, float $lat2, float $lng2): float {
    $earthRadius = 6371000.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

$points = [];
$totalDistanceMetres = 0.0;
$prev = null;
foreach ($rows as $r) {
    $lat = (float)$r['Latitude'];
    $lng = (float)$r['Longitude'];
    if ($prev !== null) {
        $totalDistanceMetres += haversine_metres($prev[0], $prev[1], $lat, $lng);
    }
    $prev = [$lat, $lng];
    $points[] = [
        'latitude'    => $lat,
        'longitude'   => $lng,
        'accuracy'    => $r['Accuracy'] !== null ? (float)$r['Accuracy'] : null,
        'speed'       => $r['Speed'] !== null ? (float)$r['Speed'] : null,
        'captured_at' => to_iso_date($r['CapturedAt']),
    ];
}

$startTime = $rows ? to_iso_date($rows[0]['CapturedAt']) : null;
$endTime = $rows ? to_iso_date($rows[count($rows) - 1]['CapturedAt']) : null;
$durationMinutes = ($startTime && $endTime)
    ? (int)round((strtotime($endTime) - strtotime($startTime)) / 60)
    : 0;

json_response(true, [
    'points' => $points,
    'summary' => [
        'start_time'      => $startTime,
        'end_time'        => $endTime,
        'duration_minutes' => $durationMinutes,
        'total_distance_km' => round($totalDistanceMetres / 1000, 2),
        'point_count'     => count($points),
    ],
]);

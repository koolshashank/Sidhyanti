<?php
/**
 * POST /api/v1/tracking/ping.php
 * {
 *   "session_id": 123,
 *   "points": [
 *     { "lat": 28.6139, "lng": 77.2090, "accuracy": 12.5, "speed": 0.0, "captured_at": "2026-10-20T10:05:00" },
 *     ...
 *   ]
 * }
 *
 * Accepts a batch of points (the mobile app queues points locally while
 * offline and flushes them here in one call once connectivity returns, to
 * keep the write count down). Every point in the batch must belong to the
 * caller's own session. The most recent point (by captured_at) overwrites
 * Staff_Live_Location, which is what the Admin live map reads.
 */

require_once __DIR__ . '/../../includes/api_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();
$staffId = (int)$claims['StaffID'];
$body = get_json_body();

$sessionId = (int)($body['session_id'] ?? 0);
$points = is_array($body['points'] ?? null) ? $body['points'] : [];
if ($sessionId <= 0 || !$points) {
    json_response(false, null, 'session_id and a non-empty points array are required', 422);
}

$sessionStmt = $pdo->prepare("SELECT StaffID FROM Duty_Session WHERE SessionID = ?");
$sessionStmt->execute([$sessionId]);
$session = $sessionStmt->fetch();
if (!$session) {
    json_response(false, null, 'Duty session not found', 404);
}
if ((int)$session['StaffID'] !== $staffId) {
    json_response(false, null, 'Forbidden', 403);
}

$insertStmt = $pdo->prepare("
    INSERT INTO Duty_Session_Point (SessionID, StaffID, Latitude, Longitude, Accuracy, Speed, CapturedAt)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");

$pdo->beginTransaction();
$inserted = 0;
$latest = null; // ['lat' => .., 'lng' => .., 'accuracy' => .., 'captured_at' => ..]

foreach ($points as $p) {
    $lat = isset($p['lat']) ? (float)$p['lat'] : null;
    $lng = isset($p['lng']) ? (float)$p['lng'] : null;
    $capturedAt = $p['captured_at'] ?? null;
    if ($lat === null || $lng === null || !$capturedAt) {
        continue; // skip malformed points rather than failing the whole batch
    }
    $accuracy = isset($p['accuracy']) ? (float)$p['accuracy'] : null;
    $speed = isset($p['speed']) ? (float)$p['speed'] : null;

    $insertStmt->execute([$sessionId, $staffId, $lat, $lng, $accuracy, $speed, $capturedAt]);
    $inserted++;

    if ($latest === null || strtotime($capturedAt) >= strtotime($latest['captured_at'])) {
        $latest = ['lat' => $lat, 'lng' => $lng, 'accuracy' => $accuracy, 'captured_at' => $capturedAt];
    }
}

if ($latest !== null) {
    $exists = $pdo->prepare("SELECT 1 FROM Staff_Live_Location WHERE StaffID = ?");
    $exists->execute([$staffId]);
    if ($exists->fetchColumn()) {
        $pdo->prepare("
            UPDATE Staff_Live_Location
            SET SessionID = ?, Latitude = ?, Longitude = ?, Accuracy = ?, CapturedAt = ?, UpdatedAt = GETDATE()
            WHERE StaffID = ?
        ")->execute([$sessionId, $latest['lat'], $latest['lng'], $latest['accuracy'], $latest['captured_at'], $staffId]);
    } else {
        $pdo->prepare("
            INSERT INTO Staff_Live_Location (StaffID, SessionID, Latitude, Longitude, Accuracy, CapturedAt, UpdatedAt)
            VALUES (?, ?, ?, ?, ?, ?, GETDATE())
        ")->execute([$staffId, $sessionId, $latest['lat'], $latest['lng'], $latest['accuracy'], $latest['captured_at']]);
    }
}

$pdo->commit();

json_response(true, ['inserted' => $inserted]);

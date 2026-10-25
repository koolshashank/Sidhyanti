<?php
/**
 * POST /api/v1/centers/checkin.php
 * Body: { center_id, latitude, longitude }
 * Records the field officer's GPS position against the center's own stored
 * Latitude/Longitude (set when the center was created/approved) -- a
 * center with no stored location records the visit with distance/within
 * geofence left null rather than blocking the check-in.
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../../includes/geo_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}

const CENTER_CHECKIN_GEOFENCE_METERS = 200.0;

$claims = require_api_auth();
$body = get_json_body();
$centerId = (int)($body['center_id'] ?? 0);
$latitude = isset($body['latitude']) ? (float)$body['latitude'] : null;
$longitude = isset($body['longitude']) ? (float)$body['longitude'] : null;

if ($centerId <= 0 || $latitude === null || $longitude === null) {
    json_response(false, null, 'center_id, latitude and longitude are required', 422);
}

$centerStmt = $pdo->prepare("SELECT CenterID, Latitude, Longitude FROM Center WHERE CenterID = ?");
$centerStmt->execute([$centerId]);
$center = $centerStmt->fetch();
if (!$center) {
    json_response(false, null, 'Center not found', 404);
}

$distanceMeters = null;
$withinGeofence = null;
if ($center['Latitude'] !== null && $center['Longitude'] !== null && (float)$center['Latitude'] !== 0.0) {
    $distanceMeters = round(haversine_metres((float)$center['Latitude'], (float)$center['Longitude'], $latitude, $longitude), 2);
    $withinGeofence = $distanceMeters <= CENTER_CHECKIN_GEOFENCE_METERS;
}

$newId = insert_and_get_id($pdo, "INSERT INTO Center_Visit_Checkin (CenterID, StaffID, Latitude, Longitude, DistanceMeters, WithinGeofence)
    VALUES (?,?,?,?,?,?)", [
    $centerId,
    (int)$claims['StaffID'],
    $latitude,
    $longitude,
    $distanceMeters,
    $withinGeofence,
], 'CheckinID');

json_response(true, [
    'checkin_id' => $newId,
    'distance_meters' => $distanceMeters,
    'within_geofence' => $withinGeofence,
], 'Checked in');

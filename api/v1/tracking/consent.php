<?php
/**
 * POST /api/v1/tracking/consent.php
 * { "consent": true }
 *
 * Records (or revokes) the Staff member's consent to be location-tracked
 * during duty hours. The app must call this once, successfully, before
 * duty_start.php will let them begin a session.
 */

require_once __DIR__ . '/../../includes/api_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();
$body = get_json_body();
$consent = !empty($body['consent']);

$exists = $pdo->prepare("SELECT 1 FROM Staff_Tracking_Consent WHERE StaffID = ?");
$exists->execute([$claims['StaffID']]);

if ($exists->fetchColumn()) {
    $stmt = $pdo->prepare("UPDATE Staff_Tracking_Consent SET ConsentGiven = ?, ConsentAt = GETDATE() WHERE StaffID = ?");
    $stmt->execute([$consent ? 1 : 0, $claims['StaffID']]);
} else {
    $stmt = $pdo->prepare("INSERT INTO Staff_Tracking_Consent (StaffID, ConsentGiven, ConsentAt) VALUES (?, ?, GETDATE())");
    $stmt->execute([$claims['StaffID'], $consent ? 1 : 0]);
}

json_response(true, ['consent_given' => $consent]);

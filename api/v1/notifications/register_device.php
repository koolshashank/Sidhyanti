<?php
/**
 * POST /api/v1/notifications/register_device.php
 * Body: { fcm_token }
 *
 * Upserts this staff member's FCM registration token, called on login and
 * whenever Firebase rotates the token. One row per staff (latest device
 * wins -- same single-device assumption the device-lock feature makes).
 */

require_once __DIR__ . '/../../includes/api_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();
$body = get_json_body();
$fcmToken = trim((string)($body['fcm_token'] ?? ''));
if ($fcmToken === '') {
    json_response(false, null, 'fcm_token is required', 422);
}

$staffId = (int)$claims['StaffID'];

$existing = $pdo->prepare("SELECT StaffID FROM Staff_Push_Token WHERE StaffID = ?");
$existing->execute([$staffId]);

if ($existing->fetchColumn()) {
    $pdo->prepare("UPDATE Staff_Push_Token SET FcmToken = ?, UpdatedAt = GETDATE() WHERE StaffID = ?")
        ->execute([$fcmToken, $staffId]);
} else {
    $pdo->prepare("INSERT INTO Staff_Push_Token (StaffID, FcmToken, UpdatedAt) VALUES (?, ?, GETDATE())")
        ->execute([$staffId, $fcmToken]);
}

json_response(true, null, 'Device registered');

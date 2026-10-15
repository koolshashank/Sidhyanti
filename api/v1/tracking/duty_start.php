<?php
/**
 * POST /api/v1/tracking/duty_start.php
 *
 * Opens a new Duty_Session for the caller and returns its id. Requires
 * consent to already be on file. Idempotent: if the caller already has an
 * Active session (e.g. the app restarted without a clean duty_end), that
 * same session is returned instead of opening a second one.
 */

require_once __DIR__ . '/../../includes/api_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();
$staffId = (int)$claims['StaffID'];

$consentStmt = $pdo->prepare("SELECT ConsentGiven FROM Staff_Tracking_Consent WHERE StaffID = ?");
$consentStmt->execute([$staffId]);
if (!$consentStmt->fetchColumn()) {
    json_response(false, null, 'Location-tracking consent is required before starting duty', 403, 'CONSENT_REQUIRED');
}

$activeStmt = $pdo->prepare("SELECT SessionID, StartTime FROM Duty_Session WHERE StaffID = ? AND Status = 'Active'");
$activeStmt->execute([$staffId]);
$active = $activeStmt->fetch();
if ($active) {
    json_response(true, [
        'session_id' => (int)$active['SessionID'],
        'start_time' => to_iso_date($active['StartTime']),
        'resumed'    => true,
    ]);
}

require_once __DIR__ . '/../../includes/serialize_helper.php';

$sessionId = insert_and_get_id(
    $pdo,
    "INSERT INTO Duty_Session (StaffID, StartTime, Status) VALUES (?, GETDATE(), 'Active')",
    [$staffId],
    'SessionID'
);

$startStmt = $pdo->prepare("SELECT StartTime FROM Duty_Session WHERE SessionID = ?");
$startStmt->execute([$sessionId]);

json_response(true, [
    'session_id' => $sessionId,
    'start_time' => to_iso_date($startStmt->fetchColumn()),
    'resumed'    => false,
]);

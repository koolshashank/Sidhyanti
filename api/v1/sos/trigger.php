<?php
/**
 * POST /api/v1/sos/trigger.php
 * Body: { latitude?, longitude? } -- both optional; an SOS must never be
 * blocked by a missing location fix (denied permission, GPS timeout, etc.),
 * it just goes out without coordinates in that case.
 *
 * Logs the press to SOS_Alert, then notifies (via the existing in-app
 * Notification bell) the triggering staff's own branch's Branch Manager(s)
 * plus every Admin, regardless of branch.
 */

require_once __DIR__ . '/../../includes/api_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();
$body = get_json_body();

$latitude = isset($body['latitude']) && $body['latitude'] !== '' ? (float)$body['latitude'] : null;
$longitude = isset($body['longitude']) && $body['longitude'] !== '' ? (float)$body['longitude'] : null;

$staffStmt = $pdo->prepare("SELECT StaffID, FullName, Phone, BranchID FROM Staff WHERE StaffID = ?");
$staffStmt->execute([(int)$claims['StaffID']]);
$staff = $staffStmt->fetch();
if (!$staff) {
    json_response(false, null, 'Staff not found', 404);
}

$pdo->beginTransaction();
try {
    $alertId = insert_and_get_id($pdo, "INSERT INTO SOS_Alert (StaffID, Latitude, Longitude)
        VALUES (?, ?, ?)", [
        $staff['StaffID'],
        $latitude,
        $longitude,
    ], 'SOSAlertID');

    // Own branch's Branch Manager(s)/ABM + every Admin, regardless of branch.
    // (Role, not Designation -- deliberately broad: any designation under
    // the "Branch Manager" role at this branch should be alerted.)
    $recipStmt = $pdo->prepare("
      SELECT s.StaffID FROM Staff s JOIN Role_Master r ON s.RoleID = r.RoleID
      WHERE s.IsActive = 1 AND s.StaffID != ?
        AND (r.RoleName = 'Admin' OR (r.RoleName = 'Branch Manager' AND s.BranchID = ?))
    ");
    $recipStmt->execute([$staff['StaffID'], $staff['BranchID']]);
    $recipientIds = array_column($recipStmt->fetchAll(), 'StaffID');

    if ($recipientIds) {
        $mapsLink = ($latitude !== null && $longitude !== null)
            ? "https://maps.google.com/?q=$latitude,$longitude"
            : null;
        $msg = "{$staff['FullName']}" . ($staff['Phone'] ? " ({$staff['Phone']})" : '') . " has triggered an SOS alert.";
        $msg .= $mapsLink ? " Location: $mapsLink" : ' Location not available.';

        $notifyIns = $pdo->prepare("
          INSERT INTO Notification (StaffID, Title, Message, NotificationTypeID, RelatedEntityType, RelatedEntityID, IsRead, CreatedAt)
          VALUES (?, 'SOS Alert', ?, 4, 'SOSAlert', ?, 0, GETDATE())
        ");
        foreach ($recipientIds as $rid) {
            $notifyIns->execute([$rid, $msg, $alertId]);
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('sos/trigger.php failed: ' . $e->getMessage());
    json_response(false, null, 'Failed to send SOS alert', 500);
}

json_response(true, [
    'sos_alert_id'    => $alertId,
    'notified_count'  => count($recipientIds),
], 'SOS alert sent');

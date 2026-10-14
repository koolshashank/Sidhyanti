<?php
/**
 * POST /api/v1/centers/create ("Create New Center")
 * Body: { center_name, center_place?, address?, state_id?, pincode?,
 *         meeting_day?, meeting_time?, contact_person?, contact_no?,
 *         latitude?, longitude? }
 *
 * BranchID and ManagedByStaffID come from the caller's own JWT claims -- a
 * field staff creates centers for their own branch and becomes its manager.
 * No existing PHP page creates centers (centers.php is read-only), so
 * CenterCode is generated here the same way the seeded data names it:
 * "CTR-{CenterID}".
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../../includes/notification_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();
$branchId = $claims['BranchID'] ?? null;
if (!$branchId) {
    json_response(false, null, 'No branch associated with your account', 422);
}

$body = get_json_body();
$centerName = trim((string)($body['center_name'] ?? ''));
if ($centerName === '') {
    json_response(false, null, 'center_name is required', 422);
}

$stateId = $body['state_id'] ?? null;
if ($stateId !== null) {
    $check = $pdo->prepare("SELECT 1 FROM State_Master WHERE StateID = ?");
    $check->execute([$stateId]);
    if (!$check->fetchColumn()) {
        json_response(false, null, 'Invalid state_id', 422);
    }
}

$pdo->beginTransaction();
try {
    // CenterCode is UNIQUE and NOT NULL, so the placeholder must be
    // collision-proof too -- it's overwritten with "CTR-{id}" right below.
    $placeholderCode = 'TMP-' . bin2hex(random_bytes(8));
    $newId = insert_and_get_id($pdo, "INSERT INTO Center
        (CenterCode, BranchID, CenterName, Address, MeetingDay, MeetingTime, ManagedByStaffID,
         ContactPerson, ContactNo, CenterPlace, Latitude, Longitude, StateID, Pincode, CreatedBy)
        VALUES (?, ?,?,?,?,?,?,?,?,?,?,?,?,?,?)", [
        $placeholderCode,
        $branchId,
        $centerName,
        $body['address'] ?? null,
        $body['meeting_day'] ?? null,
        $body['meeting_time'] ?? null,
        (int)$claims['StaffID'],
        $body['contact_person'] ?? null,
        $body['contact_no'] ?? null,
        $body['center_place'] ?? null,
        isset($body['latitude']) ? (string)$body['latitude'] : null,
        isset($body['longitude']) ? (string)$body['longitude'] : null,
        $stateId,
        $body['pincode'] ?? null,
        (int)$claims['StaffID'],
    ], 'CenterID');
    $centerCode = 'CTR-' . $newId;
    $pdo->prepare("UPDATE Center SET CenterCode = ? WHERE CenterID = ?")->execute([$centerCode, $newId]);

    // Notify this branch's Branch Manager(s) that a new center is awaiting review.
    $bmStmt = $pdo->prepare("
      SELECT s.StaffID FROM Staff s JOIN Role_Master r ON s.RoleID = r.RoleID
      WHERE r.RoleName = 'Branch Manager' AND s.BranchID = ? AND s.IsActive = 1 AND s.StaffID != ?
    ");
    $bmStmt->execute([$branchId, (int)$claims['StaffID']]);
    $notifyIds = array_column($bmStmt->fetchAll(), 'StaffID');
    if ($notifyIds) {
        foreach ($notifyIds as $sid) {
            notify_staff($pdo, $sid, 'New Center Awaiting Approval', "\"$centerName\" was created and is awaiting your approval.", 1, 'Center', $newId);
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('centers/create.php failed: ' . $e->getMessage());
    json_response(false, null, 'Failed to create center', 500);
}

json_response(true, [
    'center_id' => $newId,
    'center_code' => $centerCode,
    'center_name' => $centerName,
], 'Center created');

<?php
/**
 * GET /api/v1/centers/detail.php?center_id=
 * Full editable detail for a pending Center -- the BM/AM's "Review Center"
 * screen. Gated by "approve_new_center", branch-scoped. Latitude/Longitude
 * are returned for display only -- centers/update.php refuses to change
 * them, since they were captured live on-site when the center was created.
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../../includes/permission_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();

if (!role_has_permission($pdo, $claims['RoleID'] ?? null, 'approve_new_center')) {
    json_response(false, null, 'You do not have permission to review centers', 403);
}

$centerId = (int)($_GET['center_id'] ?? 0);
if ($centerId <= 0) {
    json_response(false, null, 'center_id is required', 422);
}

$stmt = $pdo->prepare("
  SELECT c.CenterID, c.CenterCode, c.CenterName, c.Address, c.CenterPlace, c.StateID, c.Pincode,
         c.MeetingDay, c.MeetingTime, c.ContactPerson, c.ContactNo, c.Latitude, c.Longitude,
         c.BranchID, c.ApprovalStatus, b.BranchName, s.FullName AS ManagedByName
  FROM Center c
  LEFT JOIN Branch b ON c.BranchID = b.BranchID
  LEFT JOIN Staff s ON c.ManagedByStaffID = s.StaffID
  WHERE c.CenterID = ?
");
$stmt->execute([$centerId]);
$center = $stmt->fetch();
if (!$center) {
    json_response(false, null, 'Center not found', 404);
}
assert_center_access($center, $claims);

json_response(true, [
    'center_id'        => (int)$center['CenterID'],
    'center_code'      => $center['CenterCode'],
    'approval_status'  => $center['ApprovalStatus'],
    'branch_name'      => $center['BranchName'],
    'managed_by_name'  => $center['ManagedByName'],
    'center_name'      => $center['CenterName'],
    'address'          => $center['Address'],
    'center_place'     => $center['CenterPlace'],
    'state_id'         => $center['StateID'] !== null ? (int)$center['StateID'] : null,
    'pincode'          => $center['Pincode'],
    'meeting_day'      => $center['MeetingDay'],
    'meeting_time'     => $center['MeetingTime'],
    'contact_person'   => $center['ContactPerson'],
    'contact_no'       => $center['ContactNo'],
    'latitude'         => $center['Latitude'] !== null ? (float)$center['Latitude'] : null,
    'longitude'        => $center['Longitude'] !== null ? (float)$center['Longitude'] : null,
]);

<?php
/**
 * POST /api/v1/centers/update.php
 * Body: { center_id, center_name, center_place?, address?, state_id?,
 *         pincode?, meeting_day?, meeting_time?, contact_person?, contact_no? }
 *
 * Lets the BM/AM correct a center's details while it's still awaiting their
 * review, before approving/rejecting it via centers/approve.php. Deliberately
 * does NOT accept latitude/longitude -- those were captured live on-site when
 * the CM created the center and must stay exactly as recorded.
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../../includes/permission_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();

if (!role_has_permission($pdo, $claims['RoleID'] ?? null, 'approve_new_center')) {
    json_response(false, null, 'You do not have permission to review centers', 403);
}

$body = get_json_body();
$centerId = (int)($body['center_id'] ?? 0);
$centerName = trim((string)($body['center_name'] ?? ''));

if ($centerId <= 0) {
    json_response(false, null, 'center_id is required', 422);
}
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

$centerStmt = $pdo->prepare("SELECT CenterID, BranchID, ApprovalStatus FROM Center WHERE CenterID = ?");
$centerStmt->execute([$centerId]);
$center = $centerStmt->fetch();
if (!$center) {
    json_response(false, null, 'Center not found', 404);
}
assert_center_access($center, $claims);

if ($center['ApprovalStatus'] !== 'Pending') {
    json_response(false, null, 'This center is no longer under review', 409);
}

$pdo->prepare("
  UPDATE Center
  SET CenterName = ?, Address = ?, CenterPlace = ?, StateID = ?, Pincode = ?,
      MeetingDay = ?, MeetingTime = ?, ContactPerson = ?, ContactNo = ?
  WHERE CenterID = ?
")->execute([
    $centerName,
    $body['address'] ?? null,
    $body['center_place'] ?? null,
    $stateId,
    $body['pincode'] ?? null,
    $body['meeting_day'] ?? null,
    $body['meeting_time'] ?? null,
    $body['contact_person'] ?? null,
    $body['contact_no'] ?? null,
    $centerId,
]);

json_response(true, ['center_id' => $centerId], 'Center updated');

<?php
/**
 * POST /api/v1/centers/approve.php
 * Body: { center_id, action: 'approve'|'reject', remarks? }
 * Gated by "approve_new_center", branch-scoped. Note: an unapproved center
 * is still fully usable today (see database/10_bm_approvals.sql) -- this
 * is a BM oversight/audit action, not a hard block on the center's use.
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../../includes/permission_helper.php';
require_once __DIR__ . '/../../../includes/notification_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();

if (!role_has_permission($pdo, $claims['RoleID'] ?? null, 'approve_new_center')) {
    json_response(false, null, 'You do not have permission to review centers', 403);
}

$body = get_json_body();
$centerId = (int)($body['center_id'] ?? 0);
$action = trim((string)($body['action'] ?? ''));
$remarks = trim((string)($body['remarks'] ?? '')) ?: null;

if ($centerId <= 0) {
    json_response(false, null, 'center_id is required', 422);
}
if (!in_array($action, ['approve', 'reject'], true)) {
    json_response(false, null, 'action must be approve or reject', 422);
}

$centerStmt = $pdo->prepare("SELECT CenterID, CenterName, BranchID, ManagedByStaffID FROM Center WHERE CenterID = ?");
$centerStmt->execute([$centerId]);
$center = $centerStmt->fetch();
if (!$center) {
    json_response(false, null, 'Center not found', 404);
}
assert_center_access($center, $claims);

$newStatus = $action === 'approve' ? 'Approved' : 'Rejected';

$update = $pdo->prepare("
  UPDATE Center
  SET ApprovalStatus = ?, RemarksByApprover = ?, ReviewedByStaffID = ?, ReviewedAt = GETDATE()
  WHERE CenterID = ? AND ApprovalStatus = 'Pending'
");
$update->execute([$newStatus, $remarks, (int)$claims['StaffID'], $centerId]);
if ($update->rowCount() !== 1) {
    json_response(false, null, 'This center was already reviewed', 409);
}

if ($center['ManagedByStaffID']) {
    $msg = $action === 'approve'
        ? "Your new center \"{$center['CenterName']}\" was approved."
        : "Your new center \"{$center['CenterName']}\" was rejected.";
    if ($remarks) {
        $msg .= ' Remarks: ' . $remarks;
    }
    notify_staff($pdo, (int)$center['ManagedByStaffID'], 'Center Reviewed', $msg, 1, 'Center', $centerId);
}

json_response(true, ['center_id' => $centerId, 'status' => $newStatus], 'Center ' . strtolower($newStatus));

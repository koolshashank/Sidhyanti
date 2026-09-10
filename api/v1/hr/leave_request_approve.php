<?php
/**
 * POST /api/v1/hr/leave_request_approve.php
 * Body: { leave_request_id, action: 'approve'|'reject', remarks? }
 * Gated by the "approve_leave_request" role permission, branch-scoped to
 * the requester's own Staff.BranchID (Admin can review any branch).
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../../includes/permission_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();

if (!role_has_permission($pdo, $claims['RoleID'] ?? null, 'approve_leave_request')) {
    json_response(false, null, 'You do not have permission to review leave requests', 403);
}

$body = get_json_body();
$leaveRequestId = (int)($body['leave_request_id'] ?? 0);
$action = trim((string)($body['action'] ?? ''));
$remarks = trim((string)($body['remarks'] ?? '')) ?: null;

if ($leaveRequestId <= 0) {
    json_response(false, null, 'leave_request_id is required', 422);
}
if (!in_array($action, ['approve', 'reject'], true)) {
    json_response(false, null, 'action must be approve or reject', 422);
}

$lrStmt = $pdo->prepare("
  SELECT lr.LeaveRequestID, lr.StaffID, st.BranchID
  FROM Leave_Request lr JOIN Staff st ON lr.StaffID = st.StaffID
  WHERE lr.LeaveRequestID = ?
");
$lrStmt->execute([$leaveRequestId]);
$lr = $lrStmt->fetch();
if (!$lr) {
    json_response(false, null, 'Leave request not found', 404);
}
if (!is_admin_role($claims) && (int)$lr['BranchID'] !== (int)$claims['BranchID']) {
    json_response(false, null, 'Forbidden', 403);
}

$newStatus = $action === 'approve' ? 'Approved' : 'Rejected';

$update = $pdo->prepare("
  UPDATE Leave_Request
  SET Status = ?, RemarksByApprover = ?, ApprovedByStaffID = ?, ReviewedAt = GETDATE()
  WHERE LeaveRequestID = ? AND Status = 'Pending'
");
$update->execute([$newStatus, $remarks, (int)$claims['StaffID'], $leaveRequestId]);
if ($update->rowCount() !== 1) {
    json_response(false, null, 'This leave request was already reviewed', 409);
}

$msg = $action === 'approve' ? 'Your leave request was approved.' : 'Your leave request was rejected.';
if ($remarks) {
    $msg .= ' Remarks: ' . $remarks;
}
$pdo->prepare("
  INSERT INTO Notification (StaffID, Title, Message, NotificationTypeID, RelatedEntityType, RelatedEntityID, IsRead, CreatedAt)
  VALUES (?, 'Leave Request Reviewed', ?, 1, 'LeaveRequest', ?, 0, GETDATE())
")->execute([(int)$lr['StaffID'], $msg, $leaveRequestId]);

json_response(true, ['leave_request_id' => $leaveRequestId, 'status' => $newStatus], 'Leave request ' . strtolower($newStatus));

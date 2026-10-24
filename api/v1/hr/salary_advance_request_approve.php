<?php
/**
 * POST /api/v1/hr/salary_advance_request_approve.php
 * Body: { request_id, action: 'approve'|'reject', remarks? }
 * Admin/HR only.
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../../includes/notification_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();

if (($claims['RoleName'] ?? '') !== 'Admin') {
    json_response(false, null, 'You do not have permission to review salary advance requests', 403);
}

$body = get_json_body();
$requestId = (int)($body['request_id'] ?? 0);
$action = trim((string)($body['action'] ?? ''));
$remarks = trim((string)($body['remarks'] ?? '')) ?: null;

if ($requestId <= 0) {
    json_response(false, null, 'request_id is required', 422);
}
if (!in_array($action, ['approve', 'reject'], true)) {
    json_response(false, null, 'action must be approve or reject', 422);
}

$reqStmt = $pdo->prepare("SELECT RequestID, StaffID, RequestType, Amount FROM Salary_Advance_Request WHERE RequestID = ?");
$reqStmt->execute([$requestId]);
$req = $reqStmt->fetch();
if (!$req) {
    json_response(false, null, 'Salary advance request not found', 404);
}

$newStatus = $action === 'approve' ? 'Approved' : 'Rejected';

$update = $pdo->prepare("
  UPDATE Salary_Advance_Request
  SET Status = ?, ReviewRemarks = ?, ReviewedByStaffID = ?, ReviewedAt = GETDATE()
  WHERE RequestID = ? AND Status = 'Pending'
");
$update->execute([$newStatus, $remarks, (int)$claims['StaffID'], $requestId]);
if ($update->rowCount() !== 1) {
    json_response(false, null, 'This request was already reviewed', 409);
}

$msg = $action === 'approve'
    ? 'Your ' . $req['RequestType'] . ' salary advance request for Rs. ' . number_format((float)$req['Amount'], 2) . ' was approved.'
    : 'Your ' . $req['RequestType'] . ' salary advance request was rejected.';
if ($remarks) {
    $msg .= ' Remarks: ' . $remarks;
}
notify_staff($pdo, (int)$req['StaffID'], 'Salary Advance Reviewed', $msg, 1, 'SalaryAdvanceRequest', $requestId);

json_response(true, ['request_id' => $requestId, 'status' => $newStatus], 'Salary advance request ' . strtolower($newStatus));

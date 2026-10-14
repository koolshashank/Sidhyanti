<?php
/**
 * POST /api/v1/centers/transfer.php
 * Body: { center_id, new_staff_id, remark? }
 *
 * Mirrors centers.php's web "Transfer Center" action exactly: reassigns a
 * center's managing officer to another *active* staff member in the same
 * branch (never a new branch), moving with it every Member in the center,
 * every currently-active (non-final-stage) Loan, and any not-yet-collected
 * Daily_Collection_Sheet rows -- then logs it to Center_Transfer_History
 * and notifies both the outgoing and incoming staff.
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../includes/api_error.php';
require_once __DIR__ . '/../../../includes/permission_helper.php';
require_once __DIR__ . '/../../../includes/notification_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();

if (!role_has_permission($pdo, $claims['RoleID'] ?? null, 'transfer_center')) {
    json_response(false, null, 'You do not have permission to transfer centers', 403);
}

$body = get_json_body();
$centerId = (int)($body['center_id'] ?? 0);
$newStaffId = (int)($body['new_staff_id'] ?? 0);
$remark = trim((string)($body['remark'] ?? '')) ?: null;

if ($centerId <= 0 || $newStaffId <= 0) {
    json_response(false, null, 'center_id and new_staff_id are required', 422);
}

$centerStmt = $pdo->prepare("SELECT CenterID, CenterName, BranchID, ManagedByStaffID FROM Center WHERE CenterID = ?");
$centerStmt->execute([$centerId]);
$center = $centerStmt->fetch();
if (!$center) {
    json_response(false, null, 'Center not found', 404);
}
assert_center_access($center, $claims);

$newStaffStmt = $pdo->prepare("SELECT StaffID, FullName, BranchID FROM Staff WHERE StaffID = ? AND IsActive = 1");
$newStaffStmt->execute([$newStaffId]);
$newStaff = $newStaffStmt->fetch();

if (!$newStaff || (int)$newStaff['BranchID'] !== (int)$center['BranchID']) {
    json_response(false, null, 'Please pick an active employee from the same branch as this center', 422);
}
if ((int)$center['ManagedByStaffID'] === $newStaffId) {
    json_response(false, null, 'This center is already managed by that employee', 409);
}

$oldStaffName = 'Unassigned';
if ($center['ManagedByStaffID']) {
    $oldStaffNameStmt = $pdo->prepare("SELECT FullName FROM Staff WHERE StaffID = ?");
    $oldStaffNameStmt->execute([$center['ManagedByStaffID']]);
    $oldStaffName = $oldStaffNameStmt->fetchColumn() ?: 'Unassigned';
}

$pdo->beginTransaction();
try {
    $pdo->prepare("UPDATE Center SET ManagedByStaffID = ? WHERE CenterID = ?")->execute([$newStaffId, $centerId]);

    $memberStmt = $pdo->prepare("UPDATE Member SET AssignedToStaffID = ? WHERE CenterID = ?");
    $memberStmt->execute([$newStaffId, $centerId]);
    $membersMoved = $memberStmt->rowCount();

    $loanStmt = $pdo->prepare("
      UPDATE l SET l.LoanOfficerStaffID = ?
      FROM Loan l JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
      WHERE l.CenterID = ? AND stg.IsFinal = 0
    ");
    $loanStmt->execute([$newStaffId, $centerId]);
    $loansMoved = $loanStmt->rowCount();

    $pdo->prepare("
      UPDATE Daily_Collection_Sheet SET OfficerStaffID = ?, UpdatedAt = GETDATE()
      WHERE CenterID = ? AND CollectionStatus IS NULL
    ")->execute([$newStaffId, $centerId]);

    $pdo->prepare("
      INSERT INTO Center_Transfer_History (CenterID, OldStaffID, NewStaffID, MembersTransferredCount, LoansTransferredCount, Remark, TransferredByStaffID)
      VALUES (?,?,?,?,?,?,?)
    ")->execute([
        $centerId, $center['ManagedByStaffID'], $newStaffId, $membersMoved, $loansMoved, $remark, (int)$claims['StaffID'],
    ]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('centers/transfer.php failed: ' . $e->getMessage());
    json_response(false, null, 'Failed to transfer the center', 500);
}

$notifyIds = array_unique(array_filter(
    [$center['ManagedByStaffID'], $newStaffId],
    fn($id) => $id && (int)$id !== (int)$claims['StaffID']
));
if ($notifyIds) {
    $msg = "Center {$center['CenterName']} has been transferred from $oldStaffName to {$newStaff['FullName']}. "
        . "$membersMoved member(s) and $loansMoved active loan(s) moved with it.";
    foreach ($notifyIds as $sid) {
        notify_staff($pdo, $sid, 'Center Transferred', $msg, 1, 'Center', $centerId);
    }
}

json_response(true, ['members_moved' => $membersMoved, 'loans_moved' => $loansMoved], 'Center transferred');

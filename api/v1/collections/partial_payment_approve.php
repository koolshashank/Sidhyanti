<?php
/**
 * POST /api/v1/collections/partial_payment_approve.php
 * Body: { partial_payment_approval_id, action: 'approve'|'reject', remarks? }
 *
 * On approve: re-derives the principal/interest/penalty allocation from the
 * stored RequestedAmount and runs the exact same finalize_collection() the
 * immediate full-payment path in record.php uses. On reject: the
 * installment stays uncollected so the officer can re-attempt collection.
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../includes/api_error.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';
require_once __DIR__ . '/../../includes/collection_finalize.php';
require_once __DIR__ . '/../../../includes/permission_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();

if (!role_has_permission($pdo, $claims['RoleID'] ?? null, 'approve_partial_payment')) {
    json_response(false, null, 'You do not have permission to review partial payments', 403);
}

$body = get_json_body();
$approvalId = (int)($body['partial_payment_approval_id'] ?? 0);
$action = trim((string)($body['action'] ?? ''));
$remarks = trim((string)($body['remarks'] ?? '')) ?: null;

if ($approvalId <= 0) {
    json_response(false, null, 'partial_payment_approval_id is required', 422);
}
if (!in_array($action, ['approve', 'reject'], true)) {
    json_response(false, null, 'action must be approve or reject', 422);
}

$ppaStmt = $pdo->prepare("
  SELECT ppa.PartialPaymentApprovalID, ppa.LoanID, ppa.ScheduleID, ppa.RequestedAmount, ppa.CollectionDate,
         ppa.Remarks, ppa.RequestedByStaffID, l.BranchID, l.LoanOfficerStaffID, l.LoanNo,
         rs.PrincipalDue, rs.InterestDue
  FROM Partial_Payment_Approval ppa
  JOIN Loan l ON ppa.LoanID = l.LoanID
  JOIN Repayment_Schedule rs ON ppa.ScheduleID = rs.ScheduleID
  WHERE ppa.PartialPaymentApprovalID = ?
");
$ppaStmt->execute([$approvalId]);
$ppa = $ppaStmt->fetch();
if (!$ppa) {
    json_response(false, null, 'Partial payment request not found', 404);
}
assert_loan_access($ppa, $claims);

$newStatus = $action === 'approve' ? 'Approved' : 'Rejected';
$isPaidNow = false;

$pdo->beginTransaction();
try {
    $update = $pdo->prepare("
      UPDATE Partial_Payment_Approval
      SET Status = ?, RemarksByApprover = ?, ReviewedByStaffID = ?, ReviewedAt = GETDATE()
      WHERE PartialPaymentApprovalID = ? AND Status = 'Pending'
    ");
    $update->execute([$newStatus, $remarks, (int)$claims['StaffID'], $approvalId]);
    if ($update->rowCount() !== 1) {
        throw new ApiError(409, 'This partial payment was already reviewed');
    }

    if ($action === 'approve') {
        [$principalCollected, $interestCollected, $penaltyCollected] =
            allocate_payment((float)$ppa['RequestedAmount'], (float)$ppa['PrincipalDue'], (float)$ppa['InterestDue']);

        $isPaidNow = finalize_collection(
            $pdo, (int)$ppa['LoanID'], (int)$ppa['ScheduleID'], (float)$ppa['RequestedAmount'], (string)$ppa['CollectionDate'],
            $principalCollected, $interestCollected, $penaltyCollected,
            (int)$ppa['RequestedByStaffID'], $ppa['Remarks'], true
        );
    }

    $pdo->commit();
} catch (ApiError $e) {
    $pdo->rollBack();
    json_response(false, null, $e->getMessage(), $e->httpCode);
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('collections/partial_payment_approve.php failed: ' . $e->getMessage());
    json_response(false, null, 'Failed to review partial payment', 500);
}

$msg = $action === 'approve'
    ? "Your partial payment for loan {$ppa['LoanNo']} was approved and recorded."
    : "Your partial payment for loan {$ppa['LoanNo']} was rejected. You can collect it again.";
if ($remarks) {
    $msg .= ' Remarks: ' . $remarks;
}
$pdo->prepare("
  INSERT INTO Notification (StaffID, Title, Message, NotificationTypeID, RelatedEntityType, RelatedEntityID, IsRead, CreatedAt)
  VALUES (?, 'Partial Payment Reviewed', ?, 1, 'Loan', ?, 0, GETDATE())
")->execute([(int)$ppa['RequestedByStaffID'], $msg, (int)$ppa['LoanID']]);

json_response(true, [
    'partial_payment_approval_id' => $approvalId,
    'status' => $newStatus,
    'is_paid_now' => $isPaidNow,
], 'Partial payment ' . strtolower($newStatus));

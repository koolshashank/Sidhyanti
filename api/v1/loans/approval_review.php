<?php
/**
 * POST /api/v1/loans/approval_review.php
 * Body: { loan_approval_id, action: 'approve'|'reject', remarks? }
 *
 * Handles both Loan_Approval.RequestType values:
 *  - 'Proposal': two-step above the BM limit (see loan_approval_helper.php)
 *    -- a Branch Manager reviews first; at/under the limit their approval
 *    is final, above it their approval only forwards the row to every Area
 *    Manager covering that branch for final sign-off. Either stage's
 *    rejection is terminal. Advisory only either way -- doesn't touch the
 *    loan's stage (GRT-pass auto-advance is unaffected).
 *  - 'Preclose': single-step, unchanged -- on approve, also moves the loan
 *    to the 'Preclosed' stage (same effect as the manual stage dropdown in
 *    loan_view.php).
 *
 * Gated by "approve_loan_proposal"/"approve_loan_preclose" respectively,
 * branch-scoped via assert_loan_access.
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';
require_once __DIR__ . '/../../includes/api_error.php';
require_once __DIR__ . '/../../../includes/permission_helper.php';
require_once __DIR__ . '/../../../includes/loan_approval_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();
$body = get_json_body();

$loanApprovalId = (int)($body['loan_approval_id'] ?? 0);
$action = trim((string)($body['action'] ?? ''));
$remarks = trim((string)($body['remarks'] ?? '')) ?: null;

if ($loanApprovalId <= 0) {
    json_response(false, null, 'loan_approval_id is required', 422);
}
if (!in_array($action, ['approve', 'reject'], true)) {
    json_response(false, null, 'action must be approve or reject', 422);
}

$laStmt = $pdo->prepare("
  SELECT la.LoanApprovalID, la.LoanID, la.RequestType, la.RequestedByStaffID, la.Status,
         l.LoanNo, l.BranchID, l.LoanOfficerStaffID, l.LoanAmountSanctioned, l.LoanAmountApplied
  FROM Loan_Approval la JOIN Loan l ON la.LoanID = l.LoanID
  WHERE la.LoanApprovalID = ?
");
$laStmt->execute([$loanApprovalId]);
$la = $laStmt->fetch();
if (!$la) {
    json_response(false, null, 'Approval request not found', 404);
}
assert_loan_access($la, $claims);

$permissionKey = $la['RequestType'] === 'Preclose' ? 'approve_loan_preclose' : 'approve_loan_proposal';
if (!role_has_permission($pdo, $claims['RoleID'] ?? null, $permissionKey)) {
    json_response(false, null, 'You do not have permission to review this request', 403);
}

$roleName = $claims['RoleName'] ?? '';
$notify = []; // [staffId => message]

if ($la['RequestType'] === 'Preclose') {
    if ($la['Status'] !== 'Pending') {
        json_response(false, null, 'This request was already reviewed', 409);
    }
    $newStatus = $action === 'approve' ? 'Approved' : 'Rejected';

    $pdo->beginTransaction();
    try {
        $update = $pdo->prepare("
          UPDATE Loan_Approval SET Status = ?, RemarksByApprover = ?, ReviewedByStaffID = ?, ReviewedAt = GETDATE()
          WHERE LoanApprovalID = ? AND Status = 'Pending'
        ");
        $update->execute([$newStatus, $remarks, (int)$claims['StaffID'], $loanApprovalId]);
        if ($update->rowCount() !== 1) {
            throw new ApiError(409, 'This request was already reviewed');
        }
        if ($action === 'approve') {
            $preclosedStageId = $pdo->query("SELECT StageID FROM Loan_Stage_Master WHERE StageName = 'Preclosed'")->fetchColumn();
            $pdo->prepare("UPDATE Loan SET CurrentStageID = ?, UpdatedAt = GETDATE(), UpdatedBy = ? WHERE LoanID = ?")
                ->execute([$preclosedStageId, (int)$claims['StaffID'], $la['LoanID']]);
        }
        $pdo->commit();
    } catch (ApiError $e) {
        $pdo->rollBack();
        json_response(false, null, $e->getMessage(), $e->httpCode);
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('loans/approval_review.php failed: ' . $e->getMessage());
        json_response(false, null, 'Failed to review request', 500);
    }

    $msg = "Preclose request for loan {$la['LoanNo']} was " . strtolower($newStatus) . '.';
    if ($remarks) {
        $msg .= ' Remarks: ' . $remarks;
    }
    $notify[(int)$la['RequestedByStaffID']] = ['Preclose Request Reviewed', $msg];
} else {
    // Proposal -- two-step above the limit.
    $violation = loan_proposal_status_violation($claims, $la['Status']);
    if ($violation !== null) {
        json_response(false, null, $violation, 403);
    }

    $loanAmount = to_money($la['LoanAmountSanctioned'] ?: $la['LoanAmountApplied']);
    $isForwardToAm = $roleName === 'Branch Manager' && $action === 'approve' && $loanAmount > LOAN_PROPOSAL_BM_LIMIT;
    $newStatus = $action === 'reject' ? 'Rejected' : ($isForwardToAm ? 'BM Approved' : 'Approved');

    $pdo->beginTransaction();
    try {
        if ($roleName === 'Branch Manager' && $isForwardToAm) {
            // First stage only -- final ReviewedBy/ReviewedAt/RemarksByApprover
            // stay null until the Area Manager's final sign-off.
            $update = $pdo->prepare("
              UPDATE Loan_Approval SET Status = 'BM Approved', BMReviewedByStaffID = ?, BMReviewedAt = GETDATE(), BMRemarks = ?
              WHERE LoanApprovalID = ? AND Status = 'Pending'
            ");
            $update->execute([(int)$claims['StaffID'], $remarks, $loanApprovalId]);
        } else {
            $update = $pdo->prepare("
              UPDATE Loan_Approval SET Status = ?, RemarksByApprover = ?, ReviewedByStaffID = ?, ReviewedAt = GETDATE()
              WHERE LoanApprovalID = ? AND Status = ?
            ");
            $update->execute([$newStatus, $remarks, (int)$claims['StaffID'], $loanApprovalId, $la['Status']]);
        }
        if ($update->rowCount() !== 1) {
            throw new ApiError(409, 'This request was already reviewed');
        }
        $pdo->commit();
    } catch (ApiError $e) {
        $pdo->rollBack();
        json_response(false, null, $e->getMessage(), $e->httpCode);
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('loans/approval_review.php failed: ' . $e->getMessage());
        json_response(false, null, 'Failed to review request', 500);
    }

    if ($isForwardToAm) {
        $msg = "Loan proposal for loan {$la['LoanNo']} was approved by Branch Manager and forwarded to Area Manager for final approval.";
        if ($remarks) {
            $msg .= ' Remarks: ' . $remarks;
        }
        $notify[(int)$la['RequestedByStaffID']] = ['Loan Proposal Forwarded', $msg];

        $amMsg = "A loan proposal for loan {$la['LoanNo']} (above ₹" . number_format(LOAN_PROPOSAL_BM_LIMIT) . ") is awaiting your final approval.";
        foreach (area_managers_for_branch($pdo, (int)$la['BranchID']) as $amStaffId) {
            if ($amStaffId !== (int)$claims['StaffID']) {
                $notify[$amStaffId] = ['Loan Proposal Awaiting Final Approval', $amMsg];
            }
        }
    } else {
        $msg = "Loan proposal for loan {$la['LoanNo']} was " . strtolower($newStatus) . '.';
        if ($remarks) {
            $msg .= ' Remarks: ' . $remarks;
        }
        $notify[(int)$la['RequestedByStaffID']] = ['Loan Proposal Reviewed', $msg];
    }
}

foreach ($notify as $staffId => [$title, $msg]) {
    if ($staffId <= 0) {
        continue;
    }
    $pdo->prepare("
      INSERT INTO Notification (StaffID, Title, Message, NotificationTypeID, RelatedEntityType, RelatedEntityID, IsRead, CreatedAt)
      VALUES (?, ?, ?, 1, 'Loan', ?, 0, GETDATE())
    ")->execute([$staffId, $title, $msg, $la['LoanID']]);
}

json_response(true, ['loan_approval_id' => $loanApprovalId, 'status' => $newStatus], 'Reviewed');

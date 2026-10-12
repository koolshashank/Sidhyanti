<?php
/**
 * Two-stage approval for a Loan_Approval row of RequestType='Proposal':
 * a Branch Manager always reviews first. At/under LOAN_PROPOSAL_BM_LIMIT,
 * their approval is final (Status -> 'Approved'), same as before this
 * feature existed. Above the limit, their approval only moves the row to
 * the intermediate 'BM Approved' status -- it then sits in every Area
 * Manager covering that branch's queue for final sign-off (Status ->
 * 'Approved'). A rejection at either stage is terminal ('Rejected').
 *
 * Preclose/Leave/Partial Payment approvals are untouched -- this only
 * applies to new loan proposals, per the original request.
 */
const LOAN_PROPOSAL_BM_LIMIT = 20000;

/**
 * Extra SQL condition (starting with "AND") restricting a Proposal-queue
 * query to the status this caller's role should be reviewing: a Branch
 * Manager sees fresh ('Pending') proposals, an Area Manager sees ones a BM
 * already cleared ('BM Approved') -- which, by construction, are always
 * above the limit (a loan at/under it never reaches that status). Returns
 * '' for every other role (no rows -- they don't review proposals at all).
 */
function loan_proposal_status_condition(array $claims, string $alias = 'la'): string {
    $roleName = $claims['RoleName'] ?? '';
    if ($roleName === 'Branch Manager') {
        return "AND $alias.Status = 'Pending'";
    }
    if ($roleName === 'Area Manager') {
        return "AND $alias.Status = 'BM Approved'";
    }
    return "AND 1 = 0";
}

/**
 * Server-side re-check for the actual approve/reject action -- stops a
 * direct API call from approving/rejecting a proposal that isn't actually
 * at the status this caller's role is meant to act on. Returns an error
 * message string if not allowed, null if allowed.
 */
function loan_proposal_status_violation(array $claims, string $currentStatus): ?string {
    $roleName = $claims['RoleName'] ?? '';
    if ($roleName === 'Branch Manager' && $currentStatus !== 'Pending') {
        return 'This request is no longer awaiting Branch Manager review';
    }
    if ($roleName === 'Area Manager' && $currentStatus !== 'BM Approved') {
        return 'This request is not awaiting Area Manager review -- it needs Branch Manager sign-off first';
    }
    return null;
}

/**
 * Every active Area Manager StaffID whose scope covers $branchId (own
 * Staff.BranchID, or mapped via Staff_Branch_Map) -- used to notify "a
 * proposal is now awaiting your final sign-off" once a BM forwards one.
 */
function area_managers_for_branch(PDO $pdo, int $branchId): array {
    $stmt = $pdo->prepare("
        SELECT DISTINCT s.StaffID
        FROM Staff s
        WHERE s.RoleID = (SELECT RoleID FROM Role_Master WHERE RoleName = 'Area Manager')
          AND s.IsActive = 1
          AND (s.BranchID = ? OR EXISTS (SELECT 1 FROM Staff_Branch_Map m WHERE m.StaffID = s.StaffID AND m.BranchID = ?))
    ");
    $stmt->execute([$branchId, $branchId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/** Thrown by review_loan_approval() -- carries the HTTP code the API wants; the web caller just reads ->getMessage(). */
class LoanApprovalReviewException extends Exception {
    public int $httpCode;
    public function __construct(string $message, int $httpCode = 409) {
        parent::__construct($message);
        $this->httpCode = $httpCode;
    }
}

/**
 * Shared core of approving/rejecting a Loan_Approval row -- used by both
 * api/v1/loans/approval_review.php (mobile) and loan_approvals.php (web),
 * so the two-stage BM->AM proposal logic and the GRT-before-approve /
 * Preclose-moves-the-loan-stage rules never drift between the two.
 *
 * $la must be the row joined with its Loan (LoanApprovalID, LoanID,
 * RequestType, RequestedByStaffID, Status, LoanNo, BranchID,
 * LoanOfficerStaffID, LoanAmountSanctioned, LoanAmountApplied) -- the
 * caller is responsible for loading it, calling assert_loan_access(), and
 * checking the approve_loan_proposal/approve_loan_preclose permission
 * first (both already differ slightly between the JWT-claims API context
 * and the session-based web context).
 *
 * Returns the new Loan_Approval.Status ('Approved' | 'Rejected' | 'BM Approved').
 * Throws LoanApprovalReviewException on any validation/conflict failure.
 * Inserts the resulting Notification row(s) itself.
 */
function review_loan_approval(PDO $pdo, array $la, string $action, ?string $remarks, array $claims): string {
    $roleName = $claims['RoleName'] ?? '';
    $notify = []; // [staffId => [title, message]]
    $newStatus = '';

    if ($la['RequestType'] === 'Preclose') {
        if ($la['Status'] !== 'Pending') {
            throw new LoanApprovalReviewException('This request was already reviewed', 409);
        }
        $newStatus = $action === 'approve' ? 'Approved' : 'Rejected';

        $pdo->beginTransaction();
        try {
            $update = $pdo->prepare("
              UPDATE Loan_Approval SET Status = ?, RemarksByApprover = ?, ReviewedByStaffID = ?, ReviewedAt = GETDATE()
              WHERE LoanApprovalID = ? AND Status = 'Pending'
            ");
            $update->execute([$newStatus, $remarks, (int)$claims['StaffID'], $la['LoanApprovalID']]);
            if ($update->rowCount() !== 1) {
                throw new LoanApprovalReviewException('This request was already reviewed', 409);
            }
            if ($action === 'approve') {
                $preclosedStageId = $pdo->query("SELECT StageID FROM Loan_Stage_Master WHERE StageName = 'Preclosed'")->fetchColumn();
                $pdo->prepare("UPDATE Loan SET CurrentStageID = ?, UpdatedAt = GETDATE(), UpdatedBy = ? WHERE LoanID = ?")
                    ->execute([$preclosedStageId, (int)$claims['StaffID'], $la['LoanID']]);
            }
            $pdo->commit();
        } catch (LoanApprovalReviewException $e) {
            $pdo->rollBack();
            throw $e;
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('review_loan_approval (Preclose) failed: ' . $e->getMessage());
            throw new LoanApprovalReviewException('Failed to review request', 500);
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
            throw new LoanApprovalReviewException($violation, 403);
        }

        // A proposal can only be approved once GRT has been conducted for this
        // loan and the most recent attempt passed -- rejecting never requires it.
        if ($action === 'approve') {
            $grtStmt = $pdo->prepare("
              SELECT TOP 1 gsm.GRTStatusName FROM GRT_Details gd
              JOIN GRT_Status_Master gsm ON gd.GRTStatusID = gsm.GRTStatusID
              WHERE gd.LoanID = ? ORDER BY gd.GRTID DESC
            ");
            $grtStmt->execute([$la['LoanID']]);
            if ($grtStmt->fetchColumn() !== 'P') {
                throw new LoanApprovalReviewException('GRT must be conducted and passed before this proposal can be approved', 409);
            }
        }

        $loanAmount = (float)($la['LoanAmountSanctioned'] ?: $la['LoanAmountApplied']);
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
                $update->execute([(int)$claims['StaffID'], $remarks, $la['LoanApprovalID']]);
            } else {
                $update = $pdo->prepare("
                  UPDATE Loan_Approval SET Status = ?, RemarksByApprover = ?, ReviewedByStaffID = ?, ReviewedAt = GETDATE()
                  WHERE LoanApprovalID = ? AND Status = ?
                ");
                $update->execute([$newStatus, $remarks, (int)$claims['StaffID'], $la['LoanApprovalID'], $la['Status']]);
            }
            if ($update->rowCount() !== 1) {
                throw new LoanApprovalReviewException('This request was already reviewed', 409);
            }
            $pdo->commit();
        } catch (LoanApprovalReviewException $e) {
            $pdo->rollBack();
            throw $e;
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('review_loan_approval (Proposal) failed: ' . $e->getMessage());
            throw new LoanApprovalReviewException('Failed to review request', 500);
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

    return $newStatus;
}

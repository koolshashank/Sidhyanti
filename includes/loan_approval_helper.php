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

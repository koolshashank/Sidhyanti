<?php
/**
 * Row-visibility scoping for the mobile API, based on the Staff's RoleName
 * (from Role_Master, confirmed values: Admin, Branch Manager, Loan Officer,
 * Field Officer, Accountant, HR, Area Manager).
 *
 * - Admin sees everything (no restriction).
 * - Loan Officer / Field Officer are scoped to their own book (self-scoped).
 * - Area Manager is scoped to every branch mapped to them in
 *   Staff_Branch_Map (an "Area" = several branches), falling back to just
 *   their own Staff.BranchID if nothing's been mapped yet.
 * - Everyone else (Branch Manager, Accountant, HR) is scoped to their own
 *   single branch.
 */

const OFFICER_ROLES = ['Loan Officer', 'Field Officer'];

function is_admin_role(array $claims): bool {
    return ($claims['RoleName'] ?? '') === 'Admin';
}

function is_officer_role(array $claims): bool {
    return in_array($claims['RoleName'] ?? '', OFFICER_ROLES, true);
}

/**
 * Every branch a non-admin, non-officer caller is scoped to -- a single-
 * element array for every role except Area Manager, which can be mapped to
 * several branches via Staff_Branch_Map (see database/20_am_branch_mapping.sql
 * and am_branch_mapping.php). Looked up fresh from the DB (not embedded in
 * the JWT) so an admin's mapping change takes effect on the caller's very
 * next request, no re-login needed.
 */
function scoped_branch_ids(array $claims): array {
    static $cache = [];
    $staffId = (int)($claims['StaffID'] ?? 0);
    if (($claims['RoleName'] ?? '') !== 'Area Manager') {
        return [(int)($claims['BranchID'] ?? 0)];
    }
    if (array_key_exists($staffId, $cache)) {
        return $cache[$staffId];
    }
    global $pdo;
    $stmt = $pdo->prepare("SELECT BranchID FROM Staff_Branch_Map WHERE StaffID = ?");
    $stmt->execute([$staffId]);
    $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    if (!$ids) {
        $ids = [(int)($claims['BranchID'] ?? 0)];
    }
    return $cache[$staffId] = $ids;
}

/** [whereSql, params] for "$alias.BranchID" scoped to every branch scoped_branch_ids() returns. */
function branch_in_condition(array $claims, string $alias): array {
    $ids = scoped_branch_ids($claims);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    return ["$alias.BranchID IN ($placeholders)", $ids];
}

/**
 * Returns [whereSql, params] for scoping a Member list query. $alias is the
 * table alias used for Member in the caller's query (e.g. "m").
 */
function member_scope_where(array $claims, string $alias = 'm'): array {
    if (is_admin_role($claims)) {
        return ['', []];
    }
    if (is_officer_role($claims)) {
        return ["$alias.AssignedToStaffID = ?", [(int)$claims['StaffID']]];
    }
    return branch_in_condition($claims, $alias);
}

/** Exits with 403 unless $memberRow (must include AssignedToStaffID, BranchID) is in scope for $claims. */
function assert_member_access(array $memberRow, array $claims): void {
    if (is_admin_role($claims)) {
        return;
    }
    if (is_officer_role($claims)) {
        if ((int)($memberRow['AssignedToStaffID'] ?? 0) !== (int)$claims['StaffID']) {
            json_response(false, null, 'Forbidden', 403);
        }
        return;
    }
    if (!in_array((int)($memberRow['BranchID'] ?? 0), scoped_branch_ids($claims), true)) {
        json_response(false, null, 'Forbidden', 403);
    }
}

/** Exits with 403 unless $loanRow (must include LoanOfficerStaffID, BranchID) is in scope for $claims. */
function assert_loan_access(array $loanRow, array $claims): void {
    if (is_admin_role($claims)) {
        return;
    }
    if (is_officer_role($claims)) {
        if ((int)($loanRow['LoanOfficerStaffID'] ?? 0) !== (int)$claims['StaffID']) {
            json_response(false, null, 'Forbidden', 403);
        }
        return;
    }
    if (!in_array((int)($loanRow['BranchID'] ?? 0), scoped_branch_ids($claims), true)) {
        json_response(false, null, 'Forbidden', 403);
    }
}

/**
 * Returns [whereSql, params] for scoping a Loan list query. $alias is the
 * table alias used for Loan in the caller's query (e.g. "l").
 */
function loan_scope_where(array $claims, string $alias = 'l'): array {
    if (is_admin_role($claims)) {
        return ['', []];
    }
    if (is_officer_role($claims)) {
        return ["$alias.LoanOfficerStaffID = ?", [(int)$claims['StaffID']]];
    }
    return branch_in_condition($claims, $alias);
}

/**
 * Returns [whereSql, params] for scoping a Daily_Collection_Sheet query.
 * $alias is the table alias used for Daily_Collection_Sheet (e.g. "dcs").
 */
function collection_scope_where(array $claims, string $alias = 'dcs'): array {
    if (is_admin_role($claims)) {
        return ['', []];
    }
    if (is_officer_role($claims)) {
        return ["$alias.OfficerStaffID = ?", [(int)$claims['StaffID']]];
    }
    return branch_in_condition($claims, $alias);
}

/**
 * Returns [whereSql, params] for scoping a Center query. $alias is the table
 * alias used for Center in the caller's query (e.g. "c"). Branch Manager
 * (and every other non-admin, non-officer role) is scoped to their own
 * branch (or branches, for Area Manager) -- a center has no "own staff
 * book" concept the way a Member/Loan does.
 */
function center_scope_where(array $claims, string $alias = 'c'): array {
    if (is_admin_role($claims)) {
        return ['', []];
    }
    return branch_in_condition($claims, $alias);
}

/** Exits with 403 unless $centerRow (must include BranchID) is in scope for $claims. */
function assert_center_access(array $centerRow, array $claims): void {
    if (is_admin_role($claims)) {
        return;
    }
    if (!in_array((int)($centerRow['BranchID'] ?? 0), scoped_branch_ids($claims), true)) {
        json_response(false, null, 'Forbidden', 403);
    }
}

/**
 * Returns [whereSql, params] for scoping a Leave_Request query joined to
 * Staff (Leave_Request has no BranchID of its own -- branch lives on the
 * requester's Staff row). $alias is the alias used for Staff in the
 * caller's query (e.g. "st" in "... JOIN Staff st ON lr.StaffID = st.StaffID").
 */
function leave_scope_where(array $claims, string $alias = 'st'): array {
    if (is_admin_role($claims)) {
        return ['', []];
    }
    return branch_in_condition($claims, $alias);
}

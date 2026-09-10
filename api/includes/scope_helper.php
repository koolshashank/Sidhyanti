<?php
/**
 * Row-visibility scoping for the mobile API, based on the Staff's RoleName
 * (from Role_Master, confirmed values: Admin, Branch Manager, Loan Officer,
 * Field Officer, Accountant, HR).
 *
 * - Admin sees everything (no restriction).
 * - Loan Officer / Field Officer are scoped to their own book (self-scoped).
 * - Everyone else (Branch Manager, Accountant, HR) is scoped to their branch.
 */

const OFFICER_ROLES = ['Loan Officer', 'Field Officer'];

function is_admin_role(array $claims): bool {
    return ($claims['RoleName'] ?? '') === 'Admin';
}

function is_officer_role(array $claims): bool {
    return in_array($claims['RoleName'] ?? '', OFFICER_ROLES, true);
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
    return ["$alias.BranchID = ?", [(int)$claims['BranchID']]];
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
    if ((int)($memberRow['BranchID'] ?? 0) !== (int)$claims['BranchID']) {
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
    if ((int)($loanRow['BranchID'] ?? 0) !== (int)$claims['BranchID']) {
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
    return ["$alias.BranchID = ?", [(int)$claims['BranchID']]];
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
    return ["$alias.BranchID = ?", [(int)$claims['BranchID']]];
}

/**
 * Returns [whereSql, params] for scoping a Center query. $alias is the table
 * alias used for Center in the caller's query (e.g. "c"). Branch Manager
 * (and every other non-admin, non-officer role) is scoped to their own
 * branch -- a center has no "own staff book" concept the way a Member/Loan does.
 */
function center_scope_where(array $claims, string $alias = 'c'): array {
    if (is_admin_role($claims)) {
        return ['', []];
    }
    return ["$alias.BranchID = ?", [(int)$claims['BranchID']]];
}

/** Exits with 403 unless $centerRow (must include BranchID) is in scope for $claims. */
function assert_center_access(array $centerRow, array $claims): void {
    if (is_admin_role($claims)) {
        return;
    }
    if ((int)($centerRow['BranchID'] ?? 0) !== (int)$claims['BranchID']) {
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
    return ["$alias.BranchID = ?", [(int)$claims['BranchID']]];
}

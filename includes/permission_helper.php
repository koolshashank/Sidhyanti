<?php
/**
 * Role-based feature permissions, configurable by Admin via role_permissions.php
 * (web CRM) and enforced identically by both the web CRM and the mobile API.
 * Stored in Role_Permission (RoleID, PermissionKey, IsAllowed) -- a role with
 * no row for a given key is treated as not allowed.
 */

/** Human-readable labels for every permission key, shown on role_permissions.php. */
const PERMISSION_DEFS = [
    'change_loan_scheme' => 'Change loan scheme before disbursement',
    'shift_center_meeting' => 'Shift a center\'s meeting day (moves upcoming repayment dates)',
    'transfer_center' => 'Transfer a center to another employee (moves its members and active loans)',
    'transfer_member_center' => 'Transfer a client to another center in the same branch',
    'manage_center_leave_coverage' => 'Temporarily cover an employee\'s centers while they are on leave, and revert it later',
    'manage_reloan_eligibility_rules' => 'Define the reloan amount-increase rules used by the mobile app',
    'conduct_grt' => 'Conduct GRT (Group Recognition Test) for a loan, from the mobile app',
    'approve_loan_proposal' => 'Approve or reject a submitted loan proposal',
    'approve_loan_preclose' => 'Approve or reject a loan preclose request',
    'approve_partial_payment' => 'Approve or reject a partial (short) EMI payment',
    'approve_leave_request' => "Approve or reject a staff member's leave request",
    'approve_new_center' => 'Approve or reject a newly created center',
    'disburse_loan' => 'Disburse an approved loan',
    'request_loan_preclose' => 'Request preclosure of a loan',
];

function role_has_permission(PDO $pdo, ?int $roleId, string $key): bool {
    if (!$roleId) {
        return false;
    }
    static $cache = [];
    $cacheKey = $roleId . ':' . $key;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }
    $stmt = $pdo->prepare("SELECT IsAllowed FROM Role_Permission WHERE RoleID = ? AND PermissionKey = ?");
    $stmt->execute([$roleId, $key]);
    $val = $stmt->fetchColumn();
    return $cache[$cacheKey] = ($val !== false && (bool)$val);
}

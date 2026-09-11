<?php
/**
 * GET /api/v1/centers/branch_staff.php
 *
 * Active staff in the caller's branch -- the "new officer" picker for
 * Center Transfer (a transfer can only move a center to someone in the
 * same branch, same rule the web CRM's centers.php enforces).
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../../includes/permission_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();

if (!role_has_permission($pdo, $claims['RoleID'] ?? null, 'transfer_center')) {
    json_response(false, null, 'You do not have permission to transfer centers', 403);
}

if (is_admin_role($claims)) {
    $stmt = $pdo->prepare("SELECT StaffID, FullName, BranchID FROM Staff WHERE IsActive = 1 ORDER BY FullName");
    $stmt->execute();
} else {
    $branchIds = scoped_branch_ids($claims);
    $placeholders = implode(',', array_fill(0, count($branchIds), '?'));
    $stmt = $pdo->prepare("SELECT StaffID, FullName, BranchID FROM Staff WHERE IsActive = 1 AND BranchID IN ($placeholders) ORDER BY FullName");
    $stmt->execute($branchIds);
}
$rows = $stmt->fetchAll();

json_response(true, [
    'items' => array_map(fn($r) => [
        'staff_id'   => (int)$r['StaffID'],
        'full_name'  => $r['FullName'],
        'branch_id'  => $r['BranchID'] !== null ? (int)$r['BranchID'] : null,
    ], $rows),
]);

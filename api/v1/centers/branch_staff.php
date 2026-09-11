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

$branchId = (int)($claims['BranchID'] ?? 0);
if ($branchId <= 0 && !is_admin_role($claims)) {
    json_response(true, ['items' => []]);
}

if (is_admin_role($claims)) {
    $stmt = $pdo->prepare("SELECT StaffID, FullName, BranchID FROM Staff WHERE IsActive = 1 ORDER BY FullName");
    $stmt->execute();
} else {
    $stmt = $pdo->prepare("SELECT StaffID, FullName, BranchID FROM Staff WHERE IsActive = 1 AND BranchID = ? ORDER BY FullName");
    $stmt->execute([$branchId]);
}
$rows = $stmt->fetchAll();

json_response(true, [
    'items' => array_map(fn($r) => [
        'staff_id'   => (int)$r['StaffID'],
        'full_name'  => $r['FullName'],
        'branch_id'  => $r['BranchID'] !== null ? (int)$r['BranchID'] : null,
    ], $rows),
]);

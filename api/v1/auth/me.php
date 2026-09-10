<?php
/**
 * GET /api/v1/auth/me.php
 * Returns the caller's current profile, with a freshly computed
 * menu_items list -- lets the mobile app pick up an Admin's change on
 * mobile_menu_access.php immediately (pull-to-refresh on the dashboard)
 * instead of only at the next full login.
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../../includes/mobile_menu_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();

$stmt = $pdo->prepare("
    SELECT s.StaffID, s.FullName, s.BranchID, s.DesignationID, r.RoleName, d.DesignationName
    FROM Staff s
    LEFT JOIN Role_Master r ON s.RoleID = r.RoleID
    LEFT JOIN Designation_Master d ON s.DesignationID = d.DesignationID
    WHERE s.StaffID = ? AND s.IsActive = 1
");
$stmt->execute([(int)$claims['StaffID']]);
$staff = $stmt->fetch();
if (!$staff) {
    json_response(false, null, 'Account is no longer active', 401);
}

json_response(true, [
    'staff' => [
        'id'          => (int)$staff['StaffID'],
        'full_name'   => $staff['FullName'],
        'role'        => $staff['RoleName'],
        'branch_id'   => $staff['BranchID'] !== null ? (int)$staff['BranchID'] : null,
        'designation' => $staff['DesignationName'],
        'menu_items'  => mobile_menu_items_for_designation($pdo, $staff['DesignationID'] !== null ? (int)$staff['DesignationID'] : null),
    ],
]);

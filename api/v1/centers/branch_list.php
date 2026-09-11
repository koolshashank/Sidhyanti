<?php
/**
 * GET /api/v1/centers/branch_list.php
 *
 * Every active center in the caller's branch, with its current managing
 * staff -- the picker list for Center Transfer (api/v1/centers/transfer.php).
 * Unlike centers/list.php ("my centers" for Client Sourcing, scoped to
 * ManagedByStaffID = caller), this is branch-wide: the Area Manager
 * transferring a center is not necessarily its current manager.
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';
require_once __DIR__ . '/../../../includes/permission_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();

if (!role_has_permission($pdo, $claims['RoleID'] ?? null, 'transfer_center')) {
    json_response(false, null, 'You do not have permission to transfer centers', 403);
}

[$whereScope, $scopeParams] = center_scope_where($claims, 'c');
$where = $whereScope !== '' ? "WHERE $whereScope AND c.IsActive = 1" : 'WHERE c.IsActive = 1';

$stmt = $pdo->prepare("
  SELECT c.CenterID, c.CenterCode, c.CenterName, c.BranchID, c.ManagedByStaffID, st.FullName AS ManagedByName
  FROM Center c
  LEFT JOIN Staff st ON c.ManagedByStaffID = st.StaffID
  $where
  ORDER BY c.CenterName
");
$stmt->execute($scopeParams);
$rows = $stmt->fetchAll();

json_response(true, [
    'items' => array_map(function ($r) {
        return [
            'center_id'         => (int)$r['CenterID'],
            'center_code'       => $r['CenterCode'],
            'center_name'       => $r['CenterName'],
            'branch_id'         => (int)$r['BranchID'],
            'managed_by_id'     => $r['ManagedByStaffID'] !== null ? (int)$r['ManagedByStaffID'] : null,
            'managed_by_name'   => $r['ManagedByName'],
        ];
    }, $rows),
]);

<?php
/**
 * GET /api/v1/centers/pending.php
 * Reviewer-facing queue: every center with ApprovalStatus='Pending' in the
 * caller's branch (Admin sees every branch).
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';
require_once __DIR__ . '/../../../includes/permission_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();

if (!role_has_permission($pdo, $claims['RoleID'] ?? null, 'approve_new_center')) {
    json_response(false, null, 'You do not have permission to review centers', 403);
}

[$whereScope, $scopeParams] = center_scope_where($claims, 'c');
$where = $whereScope !== '' ? "AND $whereScope" : '';

$stmt = $pdo->prepare("
  SELECT c.CenterID, c.CenterCode, c.CenterName, c.Address, c.CenterPlace, c.MeetingDay, c.MeetingTime,
         c.ContactPerson, c.ContactNo, c.CreatedAt, b.BranchName, mgr.FullName AS ManagedByName
  FROM Center c
  LEFT JOIN Branch b ON c.BranchID = b.BranchID
  LEFT JOIN Staff mgr ON c.ManagedByStaffID = mgr.StaffID
  WHERE c.ApprovalStatus = 'Pending' $where
  ORDER BY c.CreatedAt DESC
");
$stmt->execute($scopeParams);
$rows = $stmt->fetchAll();

json_response(true, [
    'items' => array_map(function ($r) {
        return [
            'center_id'     => (int)$r['CenterID'],
            'center_code'   => $r['CenterCode'],
            'center_name'   => $r['CenterName'],
            'address'       => $r['Address'],
            'center_place'  => $r['CenterPlace'],
            'meeting_day'   => $r['MeetingDay'],
            'meeting_time'  => $r['MeetingTime'],
            'contact_person' => $r['ContactPerson'],
            'contact_no'    => $r['ContactNo'],
            'branch_name'   => $r['BranchName'],
            'managed_by_name' => $r['ManagedByName'],
            'created_at'    => to_iso_date($r['CreatedAt']),
        ];
    }, $rows),
]);

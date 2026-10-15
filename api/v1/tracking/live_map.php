<?php
/**
 * GET /api/v1/tracking/live_map.php
 * Admin only.
 *
 * Every staff member currently on an Active duty session, with their most
 * recent known position (from Staff_Live_Location). Joined on an Active
 * session so a stale live-location row left behind by an ended session
 * never shows up on the map.
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();
if (!is_admin_role($claims)) {
    json_response(false, null, 'Forbidden', 403);
}

$stmt = $pdo->query("
    SELECT s.StaffID, s.FullName, s.Phone, b.BranchName,
           sll.SessionID, sll.Latitude, sll.Longitude, sll.Accuracy, sll.CapturedAt
    FROM Staff_Live_Location sll
    JOIN Duty_Session ds ON sll.SessionID = ds.SessionID AND ds.Status = 'Active'
    JOIN Staff s ON sll.StaffID = s.StaffID
    LEFT JOIN Branch b ON s.BranchID = b.BranchID
    ORDER BY sll.CapturedAt DESC
");
$rows = $stmt->fetchAll();

json_response(true, [
    'staff' => array_map(function ($r) {
        return [
            'staff_id'    => (int)$r['StaffID'],
            'full_name'   => $r['FullName'],
            'phone'       => $r['Phone'],
            'branch_name' => $r['BranchName'],
            'session_id'  => (int)$r['SessionID'],
            'latitude'    => (float)$r['Latitude'],
            'longitude'   => (float)$r['Longitude'],
            'accuracy'    => $r['Accuracy'] !== null ? (float)$r['Accuracy'] : null,
            'captured_at' => to_iso_date($r['CapturedAt']),
        ];
    }, $rows),
]);

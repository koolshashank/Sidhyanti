<?php
/**
 * GET /api/v1/centers/todays_meetings.php
 * Centers with a collection due today (same "today's work" set as the
 * Collection tab's centers_summary.php) whose MeetingDay matches today's
 * weekday, plus whether this officer has already geo-checked-in today --
 * feeds the mobile "Today's Meetings" screen.
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();
$today = date('Y-m-d');
$todayWeekday = (int)date('N'); // 1=Monday .. 7=Sunday, same numbering Center.MeetingDay uses (see centers.php)

[$scopeSql, $scopeParams] = collection_scope_where($claims, 'dcs');
$where = ['dcs.CollectionDueDate = ?', 'c.MeetingDay = ?'];
$params = [$today, $todayWeekday];
if ($scopeSql !== '') { $where[] = $scopeSql; array_push($params, ...$scopeParams); }
$whereSql = 'WHERE ' . implode(' AND ', $where);

$stmt = $pdo->prepare("
  SELECT DISTINCT dcs.CenterID, c.CenterName, c.MeetingTime, b.BranchName,
    (SELECT TOP 1 ci.CheckinAt FROM Center_Visit_Checkin ci
      WHERE ci.CenterID = dcs.CenterID AND ci.StaffID = ? AND CAST(ci.CheckinAt AS DATE) = CAST(GETDATE() AS DATE)
      ORDER BY ci.CheckinAt DESC) AS LastCheckinAt,
    (SELECT TOP 1 ci.WithinGeofence FROM Center_Visit_Checkin ci
      WHERE ci.CenterID = dcs.CenterID AND ci.StaffID = ? AND CAST(ci.CheckinAt AS DATE) = CAST(GETDATE() AS DATE)
      ORDER BY ci.CheckinAt DESC) AS LastCheckinWithinGeofence
  FROM Daily_Collection_Sheet dcs
  LEFT JOIN Center c ON dcs.CenterID = c.CenterID
  LEFT JOIN Branch b ON dcs.BranchID = b.BranchID
  $whereSql
  ORDER BY c.MeetingTime, c.CenterName
");
array_unshift($params, (int)$claims['StaffID'], (int)$claims['StaffID']);
$stmt->execute($params);
$rows = $stmt->fetchAll();

json_response(true, [
    'items' => array_map(function ($r) {
        return [
            'center_id'    => $r['CenterID'] !== null ? (int)$r['CenterID'] : null,
            'center_name'  => $r['CenterName'],
            'branch_name'  => $r['BranchName'],
            'meeting_time' => $r['MeetingTime'],
            'checked_in_at' => $r['LastCheckinAt'] ? to_iso_date($r['LastCheckinAt']) : null,
            'within_geofence' => $r['LastCheckinWithinGeofence'] !== null ? to_bool($r['LastCheckinWithinGeofence']) : null,
        ];
    }, $rows),
]);

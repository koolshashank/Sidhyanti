<?php
/**
 * GET /api/v1/reports/leaderboard.php
 *
 * Top 10 collectors (by amount collected this month) and top 10 by
 * attendance (days checked in this month), among Loan/Field Officers in
 * the caller's own branch (or every branch mapped to them, for an Area
 * Manager) -- Admin sees company-wide. Each row flags `is_me` for the
 * caller's own StaffID, if present in the top 10.
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();
$staffId = (int)$claims['StaffID'];

$branchWhere = '';
$branchParams = [];
if (!is_admin_role($claims)) {
    [$branchWhere, $branchParams] = branch_in_condition($claims, 's');
    $branchWhere = "AND $branchWhere";
}

$collectorsStmt = $pdo->prepare("
  SELECT TOP 10 s.StaffID, s.FullName, b.BranchName, SUM(c.TotalCollected) AS CollectedAmount
  FROM Collection c
  JOIN Staff s ON s.StaffID = c.CollectedByStaffID
  JOIN Role_Master r ON s.RoleID = r.RoleID
  LEFT JOIN Branch b ON s.BranchID = b.BranchID
  WHERE r.RoleName IN ('Loan Officer', 'Field Officer')
    AND c.CollectionDate >= DATEADD(DAY, 1 - DAY(GETDATE()), CAST(GETDATE() AS DATE))
    $branchWhere
  GROUP BY s.StaffID, s.FullName, b.BranchName
  ORDER BY CollectedAmount DESC
");
$collectorsStmt->execute($branchParams);
$collectorRows = $collectorsStmt->fetchAll();

$attendanceStmt = $pdo->prepare("
  SELECT TOP 10 s.StaffID, s.FullName, b.BranchName, COUNT(*) AS DaysPresent
  FROM Attendance a
  JOIN Staff s ON s.StaffID = a.StaffID
  JOIN Role_Master r ON s.RoleID = r.RoleID
  LEFT JOIN Branch b ON s.BranchID = b.BranchID
  WHERE r.RoleName IN ('Loan Officer', 'Field Officer')
    AND a.CheckInAt IS NOT NULL
    AND a.AttendanceDate >= DATEADD(DAY, 1 - DAY(GETDATE()), CAST(GETDATE() AS DATE))
    $branchWhere
  GROUP BY s.StaffID, s.FullName, b.BranchName
  ORDER BY DaysPresent DESC
");
$attendanceStmt->execute($branchParams);
$attendanceRows = $attendanceStmt->fetchAll();

json_response(true, [
    'top_collectors' => array_map(function ($r) use ($staffId) {
        return [
            'staff_id'         => (int)$r['StaffID'],
            'full_name'        => $r['FullName'],
            'branch_name'      => $r['BranchName'],
            'collected_amount' => to_money($r['CollectedAmount']),
            'is_me'            => (int)$r['StaffID'] === $staffId,
        ];
    }, $collectorRows),
    'top_attendance' => array_map(function ($r) use ($staffId) {
        return [
            'staff_id'     => (int)$r['StaffID'],
            'full_name'    => $r['FullName'],
            'branch_name'  => $r['BranchName'],
            'days_present' => (int)$r['DaysPresent'],
            'is_me'        => (int)$r['StaffID'] === $staffId,
        ];
    }, $attendanceRows),
]);

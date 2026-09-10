<?php
/**
 * POST /api/v1/collections/attendance_mark.php
 * Body: { center_id, date (YYYY-MM-DD), records: [{loan_id, status: 'Present'|'Absent'}, ...] }
 *
 * Upserts attendance for each loan. Only loan_ids that are actually due for
 * collection at this center on this date (within the caller's scope) are
 * accepted -- anything else is silently skipped, not trusted from the client.
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();
$body = get_json_body();

$centerId = (int)($body['center_id'] ?? 0);
$date = trim((string)($body['date'] ?? ''));
$records = $body['records'] ?? [];

if ($centerId <= 0) {
    json_response(false, null, 'center_id is required', 422);
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    json_response(false, null, 'date (YYYY-MM-DD) is required', 422);
}
if (!is_array($records) || !$records) {
    json_response(false, null, 'records is required', 422);
}

[$scopeSql, $scopeParams] = collection_scope_where($claims, 'dcs');
$where = ['dcs.CollectionDueDate = ?', 'dcs.CenterID = ?'];
$params = [$date, $centerId];
if ($scopeSql !== '') { $where[] = $scopeSql; array_push($params, ...$scopeParams); }
$whereSql = 'WHERE ' . implode(' AND ', $where);

$dueStmt = $pdo->prepare("SELECT dcs.LoanID, dcs.BorrowerName FROM Daily_Collection_Sheet dcs $whereSql");
$dueStmt->execute($params);
$dueLoans = [];
foreach ($dueStmt->fetchAll() as $r) {
    $dueLoans[(int)$r['LoanID']] = $r['BorrowerName'];
}

$updateStmt = $pdo->prepare("
    UPDATE Center_Meeting_Attendance SET Status=?, MarkedByStaffID=?, MarkedAt=GETDATE()
    WHERE CenterID=? AND AttendanceDate=? AND LoanID=?
");
$insertStmt = $pdo->prepare("
    INSERT INTO Center_Meeting_Attendance (CenterID, AttendanceDate, LoanID, BorrowerName, Status, MarkedByStaffID)
    VALUES (?,?,?,?,?,?)
");

$pdo->beginTransaction();
try {
    foreach ($records as $rec) {
        $loanId = (int)($rec['loan_id'] ?? 0);
        $status = $rec['status'] ?? '';
        if (!isset($dueLoans[$loanId]) || !in_array($status, ['Present', 'Absent'], true)) {
            continue;
        }
        $updateStmt->execute([$status, (int)$claims['StaffID'], $centerId, $date, $loanId]);
        if ($updateStmt->rowCount() === 0) {
            $insertStmt->execute([$centerId, $date, $loanId, $dueLoans[$loanId], $status, (int)$claims['StaffID']]);
        }
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('collections/attendance_mark.php failed: ' . $e->getMessage());
    json_response(false, null, 'Failed to save attendance', 500);
}

$markedStmt = $pdo->prepare("SELECT COUNT(*) FROM Center_Meeting_Attendance WHERE CenterID=? AND AttendanceDate=?");
$markedStmt->execute([$centerId, $date]);
$markedCount = (int)$markedStmt->fetchColumn();

json_response(true, [
    'total_due'    => count($dueLoans),
    'marked_count' => $markedCount,
    'is_complete'  => $markedCount >= count($dueLoans),
], 'Attendance saved');

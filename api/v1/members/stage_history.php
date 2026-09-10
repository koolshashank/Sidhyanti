<?php
/**
 * GET /api/v1/members/stage_history?member_id={member_id}
 *
 * Returns the lead-stage timeline for this member (Lead_Stage_History,
 * auto-logged by DB triggers on Member insert/stage change). Most members
 * only have a single "created" row so far -- that's expected, not a bug.
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();
$memberId = (int)($_GET['member_id'] ?? 0);
if ($memberId <= 0) {
    json_response(false, null, 'member_id is required', 422);
}

$memberStmt = $pdo->prepare("SELECT AssignedToStaffID, BranchID FROM Member WHERE MemberID = ?");
$memberStmt->execute([$memberId]);
$member = $memberStmt->fetch();
if (!$member) {
    json_response(false, null, 'Member not found', 404);
}
assert_member_access($member, $claims);

$stmt = $pdo->prepare("
  SELECT h.HistoryID, fromStg.StageName AS FromStage, toStg.StageName AS ToStage,
         h.ChangedAt, s.FullName AS ChangedBy, h.Remarks
  FROM Lead_Stage_History h
  LEFT JOIN Lead_Stage_Master fromStg ON h.FromStageID = fromStg.StageID
  LEFT JOIN Lead_Stage_Master toStg ON h.ToStageID = toStg.StageID
  LEFT JOIN Staff s ON h.ChangedByStaffID = s.StaffID
  WHERE h.MemberID = ?
  ORDER BY h.ChangedAt DESC, h.HistoryID DESC
");
$stmt->execute([$memberId]);
$rows = $stmt->fetchAll();

json_response(true, [
    'items' => array_map(function ($r) {
        return [
            'from_stage' => $r['FromStage'],
            'to_stage'   => $r['ToStage'],
            'changed_at' => to_iso_date($r['ChangedAt']),
            'changed_by' => $r['ChangedBy'],
            'remarks'    => $r['Remarks'],
        ];
    }, $rows),
]);

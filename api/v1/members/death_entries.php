<?php
/**
 * GET /api/v1/members/death_entries.php?member_id={member_id}
 * Returns any death entries submitted for this member (so the mobile app
 * can show "already reported, status: Pending/Approved/Rejected" instead of
 * letting the CM submit a duplicate).
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
  SELECT de.*, r.FullName AS ReviewerName
  FROM Death_Entry de
  LEFT JOIN Staff r ON de.ReviewedByStaffID = r.StaffID
  WHERE de.MemberID = ?
  ORDER BY de.DeathEntryID DESC
");
$stmt->execute([$memberId]);
$rows = $stmt->fetchAll();

json_response(true, [
    'items' => array_map(function ($r) {
        return [
            'death_entry_id'      => (int)$r['DeathEntryID'],
            'date_of_death'       => to_iso_date($r['DateOfDeath']),
            'cause'                => $r['Cause'],
            'informant_name'       => $r['InformantName'],
            'informant_relation'   => $r['InformantRelation'],
            'proof_url'            => $r['ProofDocumentPath'],
            'status'               => $r['Status'],
            'remarks'              => $r['RemarksByApprover'],
            'reviewer_name'        => $r['ReviewerName'],
            'created_at'           => to_iso_date($r['CreatedAt']),
        ];
    }, $rows),
]);

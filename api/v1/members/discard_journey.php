<?php
/**
 * POST /api/v1/members/discard_journey.php
 * Body: { member_id }
 *
 * Soft-deletes (IsActive = 0) an incomplete lead the field officer decided
 * not to continue with (client backed out, wrong data, etc.) so it stops
 * showing up as a "resume this lead" prompt. Not a hard delete -- the record
 * stays around for audit/history, just excluded from pending_journeys.php.
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();
$body = get_json_body();
$memberId = (int)($body['member_id'] ?? 0);
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

$pdo->prepare("UPDATE Member SET IsActive = 0, UpdatedBy = ?, UpdatedAt = GETDATE() WHERE MemberID = ?")
    ->execute([(int)$claims['StaffID'], $memberId]);

json_response(true, null, 'Lead discarded');

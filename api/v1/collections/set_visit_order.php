<?php
/**
 * POST /api/v1/collections/set_visit_order.php
 * Body: { center_id, member_ids: [ordered array of MemberID, visit sequence] }
 *
 * Sets Member.VisitOrder = each member's position in the given list, for
 * every member in this center -- due.php then sorts by this instead of
 * alphabetically. Every id in member_ids must actually belong to this
 * center; anything else is rejected outright rather than silently skipped.
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();
$body = get_json_body();
$centerId = (int)($body['center_id'] ?? 0);
$memberIds = is_array($body['member_ids'] ?? null) ? array_map('intval', $body['member_ids']) : [];

if ($centerId <= 0 || !$memberIds) {
    json_response(false, null, 'center_id and a non-empty member_ids array are required', 422);
}

$centerStmt = $pdo->prepare("SELECT BranchID FROM Center WHERE CenterID = ?");
$centerStmt->execute([$centerId]);
$center = $centerStmt->fetch();
if (!$center) {
    json_response(false, null, 'Center not found', 404);
}
assert_center_access($center, $claims);

$placeholders = implode(',', array_fill(0, count($memberIds), '?'));
$checkStmt = $pdo->prepare("SELECT MemberID FROM Member WHERE CenterID = ? AND MemberID IN ($placeholders)");
$checkStmt->execute(array_merge([$centerId], $memberIds));
$validIds = array_map('intval', $checkStmt->fetchAll(PDO::FETCH_COLUMN));

if (count($validIds) !== count($memberIds) || array_diff($memberIds, $validIds)) {
    json_response(false, null, 'Every member_id must belong to this center', 422);
}

$pdo->beginTransaction();
try {
    $updateStmt = $pdo->prepare("UPDATE Member SET VisitOrder = ? WHERE MemberID = ?");
    foreach ($memberIds as $index => $memberId) {
        $updateStmt->execute([$index, $memberId]);
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('set_visit_order.php failed: ' . $e->getMessage());
    json_response(false, null, 'Failed to save visit order', 500);
}

json_response(true, null, 'Visit order saved');

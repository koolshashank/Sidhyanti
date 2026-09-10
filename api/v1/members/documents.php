<?php
/**
 * GET /api/v1/members/documents?member_id={member_id}
 *
 * Returns this member's own KYC documents (Member_KYC_Document rows where
 * MemberID matches -- co-borrower documents are out of scope here). Most
 * DocFilePath values are legacy URLs migrated from an old file server; some
 * of those hosts are no longer reachable, so the mobile app must handle a
 * dead link gracefully (same pattern as the CGT image viewer) rather than
 * assume every document resolves.
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
  SELECT k.KYCDocID, dt.DocTypeName, k.DocNumber, k.DocFilePath, k.IsVerified, k.UploadedAt
  FROM Member_KYC_Document k
  JOIN KYC_Document_Type_Master dt ON k.DocTypeID = dt.DocTypeID
  WHERE k.MemberID = ?
  ORDER BY dt.DocTypeName
");
$stmt->execute([$memberId]);
$rows = $stmt->fetchAll();

json_response(true, [
    'items' => array_map(function ($r) {
        return [
            'doc_id'      => (int)$r['KYCDocID'],
            'doc_type'    => $r['DocTypeName'],
            'doc_number'  => $r['DocNumber'],
            'file_url'    => $r['DocFilePath'],
            'is_verified' => to_bool($r['IsVerified']),
            'uploaded_at' => to_iso_date($r['UploadedAt']),
        ];
    }, $rows),
]);

<?php
/**
 * POST /api/v1/members/death_entry_create.php (multipart/form-data)
 * Fields: member_id, date_of_death (YYYY-MM-DD), cause?, informant_name?,
 *         informant_relation?, proof (file, optional -- image)
 *
 * Unlike every other mobile-create endpoint this one is multipart (not
 * JSON) because it accepts a photo upload -- saved via
 * store_document_upload() under Document_images/Death_Document/{member_id}/.
 *
 * Submits into a Pending queue for an Admin/Branch Manager to review on the
 * web CRM (death_entries.php) -- approving/rejecting does not itself change
 * the Member or Loan records (that's a deliberate v1 scope decision).
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../includes/api_error.php';
require_once __DIR__ . '/../../includes/document_storage_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();

$memberId = (int)($_POST['member_id'] ?? 0);
$dateOfDeath = trim((string)($_POST['date_of_death'] ?? ''));
if ($memberId <= 0) {
    json_response(false, null, 'member_id is required', 422);
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateOfDeath)) {
    json_response(false, null, 'date_of_death (YYYY-MM-DD) is required', 422);
}

$memberStmt = $pdo->prepare("SELECT AssignedToStaffID, BranchID FROM Member WHERE MemberID = ?");
$memberStmt->execute([$memberId]);
$member = $memberStmt->fetch();
if (!$member) {
    json_response(false, null, 'Member not found', 404);
}
assert_member_access($member, $claims);

try {
    $proofPath = store_document_upload($_FILES['proof'] ?? [], 'Death_Document', (string)$memberId, 'death');
} catch (ApiError $e) {
    json_response(false, null, $e->getMessage(), $e->httpCode);
}

try {
    $newId = insert_and_get_id($pdo, "INSERT INTO Death_Entry
        (MemberID, DateOfDeath, Cause, InformantName, InformantRelation, ProofDocumentPath, Status, ReportedByStaffID)
        VALUES (?,?,?,?,?,?, 'Pending', ?)", [
        $memberId,
        $dateOfDeath,
        $_POST['cause'] ?? null,
        $_POST['informant_name'] ?? null,
        $_POST['informant_relation'] ?? null,
        $proofPath,
        (int)$claims['StaffID'],
    ], 'DeathEntryID');
} catch (Throwable $e) {
    error_log('death_entry_create.php failed: ' . $e->getMessage());
    json_response(false, null, 'Failed to save death entry', 500);
}

json_response(true, ['death_entry_id' => $newId], 'Death entry submitted for review');

<?php
/**
 * POST /api/v1/members/death_entry_create.php (multipart/form-data)
 * Fields: member_id, date_of_death (YYYY-MM-DD), cause?, informant_name?,
 *         informant_relation?, proof (file, optional -- image)
 *
 * Unlike every other mobile-create endpoint this one is multipart (not
 * JSON) because it accepts a photo upload. This is a brand-new feature with
 * no legacy file-server dependency (unlike CGT/KYC docs, which point at an
 * old external Node host), so the proof photo is saved directly under this
 * app's own uploads/ folder and served back as a normal URL.
 *
 * Submits into a Pending queue for an Admin/Branch Manager to review on the
 * web CRM (death_entries.php) -- approving/rejecting does not itself change
 * the Member or Loan records (that's a deliberate v1 scope decision).
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';

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

$proofPath = null;
if (isset($_FILES['proof']) && $_FILES['proof']['error'] === UPLOAD_ERR_OK) {
    $allowedExt = ['jpg', 'jpeg', 'png', 'pdf'];
    $ext = strtolower(pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        json_response(false, null, 'Proof file must be an image or PDF', 422);
    }
    $uploadDir = __DIR__ . '/../../../uploads/death_proofs/';
    $filename = 'death_' . $memberId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($_FILES['proof']['tmp_name'], $uploadDir . $filename)) {
        json_response(false, null, 'Failed to save proof file', 500);
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $proofPath = $scheme . '://' . $_SERVER['HTTP_HOST'] . '/sidhyanti-crm-v2/uploads/death_proofs/' . $filename;
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

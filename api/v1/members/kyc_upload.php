<?php
/**
 * POST /api/v1/members/kyc_upload.php (multipart/form-data)
 * Fields: member_id, doc_type_id, document (file), coborrower_id? (omit for
 *         the member's own document), doc_number?
 *
 * Saves the file under Document_images/KYC/{member_id}/ and upserts
 * Member_KYC_Document -- a second upload for the same
 * (member, doc_type, coborrower) replaces the previous file's DB row (the
 * old file itself is left on disk, only the pointer moves), so the member
 * always has exactly one current document per type.
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
$docTypeId = (int)($_POST['doc_type_id'] ?? 0);
$coBorrowerId = isset($_POST['coborrower_id']) && $_POST['coborrower_id'] !== '' ? (int)$_POST['coborrower_id'] : null;

if ($memberId <= 0) {
    json_response(false, null, 'member_id is required', 422);
}
if ($docTypeId <= 0) {
    json_response(false, null, 'doc_type_id is required', 422);
}

$memberStmt = $pdo->prepare("SELECT AssignedToStaffID, BranchID FROM Member WHERE MemberID = ?");
$memberStmt->execute([$memberId]);
$member = $memberStmt->fetch();
if (!$member) {
    json_response(false, null, 'Member not found', 404);
}
assert_member_access($member, $claims);

$docTypeCheck = $pdo->prepare("SELECT 1 FROM KYC_Document_Type_Master WHERE DocTypeID = ?");
$docTypeCheck->execute([$docTypeId]);
if (!$docTypeCheck->fetchColumn()) {
    json_response(false, null, 'Invalid doc_type_id', 422);
}

try {
    $docUrl = store_document_upload($_FILES['document'] ?? [], 'KYC', (string)$memberId, 'kyc');
} catch (ApiError $e) {
    json_response(false, null, $e->getMessage(), $e->httpCode);
}
if ($docUrl === null) {
    json_response(false, null, 'document file is required', 422);
}

$docNumber = trim((string)($_POST['doc_number'] ?? '')) ?: null;

try {
    $existingStmt = $pdo->prepare("
      SELECT KYCDocID FROM Member_KYC_Document
      WHERE MemberID = ? AND DocTypeID = ?
        AND ((CoBorrowerID IS NULL AND ? IS NULL) OR CoBorrowerID = ?)
    ");
    $existingStmt->execute([$memberId, $docTypeId, $coBorrowerId, $coBorrowerId]);
    $existingId = $existingStmt->fetchColumn();

    if ($existingId) {
        $pdo->prepare("UPDATE Member_KYC_Document SET DocFilePath = ?, DocNumber = COALESCE(?, DocNumber), UploadedAt = GETDATE(), IsVerified = 0 WHERE KYCDocID = ?")
            ->execute([$docUrl, $docNumber, $existingId]);
        $kycDocId = (int)$existingId;
    } else {
        $kycDocId = insert_and_get_id($pdo, "INSERT INTO Member_KYC_Document
            (MemberID, DocTypeID, DocNumber, DocFilePath, IsVerified, UploadedAt, CoBorrowerID)
            VALUES (?,?,?,?, 0, GETDATE(), ?)", [
            $memberId, $docTypeId, $docNumber, $docUrl, $coBorrowerId,
        ], 'KYCDocID');
    }
} catch (Throwable $e) {
    error_log('members/kyc_upload.php failed: ' . $e->getMessage());
    json_response(false, null, 'Failed to save document record', 500);
}

json_response(true, ['kyc_doc_id' => $kycDocId, 'file_url' => $docUrl], 'Document uploaded');

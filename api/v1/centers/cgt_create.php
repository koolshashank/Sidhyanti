<?php
/**
 * POST /api/v1/centers/cgt_create (multipart/form-data)
 * Fields: center_id, cgt_date? (default today), remarks?, document (file, optional)
 *
 * The photo is saved under Document_images/CGT/{center_id}/ via
 * store_document_upload() -- a clean, new upload path, independent of the
 * legacy DocumentFilePath values already in the table that point at the old
 * image server (168.220.248.128:3000/CGT_Images/).
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/api_error.php';
require_once __DIR__ . '/../../includes/document_storage_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();
$branchId = $claims['BranchID'] ?? null;

$centerId = (int)($_POST['center_id'] ?? 0);
if ($centerId <= 0) {
    json_response(false, null, 'center_id is required', 422);
}

$check = $pdo->prepare("SELECT 1 FROM Center WHERE CenterID = ? AND ManagedByStaffID = ?");
$check->execute([$centerId, (int)$claims['StaffID']]);
if (!$check->fetchColumn()) {
    json_response(false, null, 'You do not manage this center', 403);
}

$cgtDate = trim((string)($_POST['cgt_date'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $cgtDate) || $cgtDate > date('Y-m-d')) {
    json_response(false, null, 'cgt_date must be a valid date not in the future', 422);
}

try {
    $documentUrl = store_document_upload($_FILES['document'] ?? [], 'CGT', (string)$centerId, 'cgt');
} catch (ApiError $e) {
    json_response(false, null, $e->getMessage(), $e->httpCode);
}

try {
    $newId = insert_and_get_id($pdo, "INSERT INTO Center_CGT_Details
        (CenterID, ConductedByStaffID, CGTDate, Remarks, DocumentFilePath)
        VALUES (?,?,?,?,?)", [
        $centerId,
        (int)$claims['StaffID'],
        $cgtDate,
        $_POST['remarks'] ?? null,
        $documentUrl,
    ], 'CGTID');
} catch (Throwable $e) {
    error_log('centers/cgt_create.php failed: ' . $e->getMessage());
    json_response(false, null, 'Failed to save CGT', 500);
}

json_response(true, ['cgt_id' => $newId, 'center_id' => $centerId], 'CGT recorded');

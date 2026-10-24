<?php
/**
 * POST /api/v1/collections/upload_proof.php (multipart/form-data)
 * Fields: collection_id, proof (file)
 *
 * Attaches an optional photo/signature proof to an already-recorded
 * Collection row -- a follow-up step after collections/record.php, not part
 * of that same request, since the capture happens in-app right after the
 * payment is confirmed. Saved under Document_images/Collection/{loan_id}/.
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../includes/api_error.php';
require_once __DIR__ . '/../../includes/document_storage_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();
$collectionId = (int)($_POST['collection_id'] ?? 0);
if ($collectionId <= 0) {
    json_response(false, null, 'collection_id is required', 422);
}

$collectionStmt = $pdo->prepare("
    SELECT c.LoanID, l.BranchID, l.LoanOfficerStaffID
    FROM Collection c JOIN Loan l ON c.LoanID = l.LoanID
    WHERE c.CollectionID = ?
");
$collectionStmt->execute([$collectionId]);
$collection = $collectionStmt->fetch();
if (!$collection) {
    json_response(false, null, 'Collection not found', 404);
}
assert_loan_access($collection, $claims);

try {
    $proofUrl = store_document_upload($_FILES['proof'] ?? [], 'Collection', (string)$collection['LoanID'], 'proof');
} catch (ApiError $e) {
    json_response(false, null, $e->getMessage(), $e->httpCode);
}
if ($proofUrl === null) {
    json_response(false, null, 'proof file is required', 422);
}

$pdo->prepare("UPDATE Collection SET ProofPhotoPath = ? WHERE CollectionID = ?")->execute([$proofUrl, $collectionId]);

json_response(true, ['proof_url' => $proofUrl], 'Proof uploaded');

<?php
/**
 * POST /api/v1/centers/cgt_create ("Center Group Training" verification)
 * Body: { center_id, cgt_date? (default today), remarks? }
 *
 * Image capture/upload is intentionally not handled here yet -- the existing
 * DocumentFilePath values point at a separate image server
 * (168.220.248.128:3000/CGT_Images/) that no code in this repo uploads to,
 * and its upload contract is undocumented. DocumentFilePath is left NULL
 * until that's resolved.
 */

require_once __DIR__ . '/../../includes/api_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();
$branchId = $claims['BranchID'] ?? null;

$body = get_json_body();
$centerId = (int)($body['center_id'] ?? 0);
if ($centerId <= 0) {
    json_response(false, null, 'center_id is required', 422);
}

$check = $pdo->prepare("SELECT 1 FROM Center WHERE CenterID = ? AND ManagedByStaffID = ?");
$check->execute([$centerId, (int)$claims['StaffID']]);
if (!$check->fetchColumn()) {
    json_response(false, null, 'You do not manage this center', 403);
}

$cgtDate = trim((string)($body['cgt_date'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $cgtDate) || $cgtDate > date('Y-m-d')) {
    json_response(false, null, 'cgt_date must be a valid date not in the future', 422);
}

try {
    $newId = insert_and_get_id($pdo, "INSERT INTO Center_CGT_Details
        (CenterID, ConductedByStaffID, CGTDate, Remarks)
        VALUES (?,?,?,?)", [
        $centerId,
        (int)$claims['StaffID'],
        $cgtDate,
        $body['remarks'] ?? null,
    ], 'CGTID');
} catch (Throwable $e) {
    error_log('centers/cgt_create.php failed: ' . $e->getMessage());
    json_response(false, null, 'Failed to save CGT', 500);
}

json_response(true, ['cgt_id' => $newId, 'center_id' => $centerId], 'CGT recorded');

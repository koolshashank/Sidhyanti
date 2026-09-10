<?php
/**
 * GET /api/v1/members/districts?state_id={id}
 * Districts for the given state -- District dropdown depends on the State
 * the user picked in the Borrower Enrollment form.
 */

require_once __DIR__ . '/../../includes/api_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

require_api_auth();

$stateId = (int)($_GET['state_id'] ?? 0);
if ($stateId <= 0) {
    json_response(false, null, 'state_id is required', 422);
}

$stmt = $pdo->prepare("SELECT DistrictID, DistrictName FROM District_Master WHERE StateID = ? ORDER BY DistrictName");
$stmt->execute([$stateId]);
$districts = $stmt->fetchAll();

json_response(true, [
    'districts' => array_map(fn($d) => ['id' => (int)$d['DistrictID'], 'name' => $d['DistrictName']], $districts),
]);

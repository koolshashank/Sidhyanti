<?php
/**
 * GET /api/v1/centers/lookups
 * Dropdown data for the "Create New Center" form: the global list of states
 * (Center.StateID is a foreign key into State_Master).
 */

require_once __DIR__ . '/../../includes/api_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

require_api_auth();

$states = $pdo->query("SELECT StateID, StateName FROM State_Master ORDER BY StateName")->fetchAll();

json_response(true, [
    'states' => array_map(fn($s) => ['id' => (int)$s['StateID'], 'name' => $s['StateName']], $states),
]);

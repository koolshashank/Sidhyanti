<?php
/**
 * GET /api/v1/members/lookups
 * Dropdown data for the "Client Sourcing" (new lead / borrower enrollment)
 * form: centers in the caller's own branch, lead sources, occupations,
 * educations, religions, castes, house types, and states. Districts are
 * fetched separately (see districts.php) since they depend on the state
 * the user picks.
 */

require_once __DIR__ . '/../../includes/api_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();
$branchId = $claims['BranchID'] ?? null;
if (!$branchId) {
    json_response(false, null, 'No branch associated with your account', 422);
}

function name_id_list(PDO $pdo, string $sql, string $idKey, string $nameKey): array {
    return array_map(
        fn($r) => ['id' => (int)$r[$idKey], 'name' => $r[$nameKey]],
        $pdo->query($sql)->fetchAll()
    );
}

$centersStmt = $pdo->prepare("SELECT CenterID, CenterName FROM Center WHERE BranchID = ? AND IsActive = 1 ORDER BY CenterName");
$centersStmt->execute([$branchId]);
$centers = $centersStmt->fetchAll();

json_response(true, [
    'centers'      => array_map(fn($c) => ['id' => (int)$c['CenterID'], 'name' => $c['CenterName']], $centers),
    'lead_sources' => name_id_list($pdo, "SELECT SourceID, SourceName FROM Lead_Source_Master ORDER BY SourceName", 'SourceID', 'SourceName'),
    'occupations'  => name_id_list($pdo, "SELECT OccupationID, OccupationName FROM Occupation_Master ORDER BY OccupationName", 'OccupationID', 'OccupationName'),
    'educations'   => name_id_list($pdo, "SELECT EducationID, EducationName FROM Education_Master ORDER BY EducationID", 'EducationID', 'EducationName'),
    'religions'    => name_id_list($pdo, "SELECT ReligionID, ReligionName FROM Religion_Master ORDER BY ReligionName", 'ReligionID', 'ReligionName'),
    'castes'       => name_id_list($pdo, "SELECT CasteID, CasteName FROM Caste_Master ORDER BY CasteName", 'CasteID', 'CasteName'),
    'house_types'  => name_id_list($pdo, "SELECT HouseTypeID, HouseTypeName FROM House_Type_Master ORDER BY HouseTypeID", 'HouseTypeID', 'HouseTypeName'),
    'states'       => name_id_list($pdo, "SELECT StateID, StateName FROM State_Master ORDER BY StateName", 'StateID', 'StateName'),
    'kyc_doc_types' => name_id_list($pdo, "SELECT DocTypeID, DocTypeName FROM KYC_Document_Type_Master ORDER BY DocTypeID", 'DocTypeID', 'DocTypeName'),
]);

<?php
/**
 * POST /api/v1/coborrowers/create (captured right after a new lead is saved)
 * Body: { member_id, first_name?, last_name?, relation_to_member?, gender?, dob?,
 *         marital_status?, mobile?, occupation_id?, mother_name?, education_id?,
 *         religion_id?, caste_id?, house_type_id?, address?, village_city?,
 *         pincode?, aadhar_no?, middle_name?, pan_no?, voter_id?,
 *         ration_card_no?, dl_no?, electricity_bill_no? }
 *
 * Only member_id is truly required (Co_Borrower's only NOT NULL column besides
 * its own PK) -- everything else is nullable in the schema. Intentionally
 * lenient during development so testers aren't forced to fill every field on
 * every test submission; tighten with proper required-field UX once the form
 * is finalized for production use.
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();
$body = get_json_body();

function opt_str($body, $key) {
    $v = $body[$key] ?? null;
    if ($v === null) return null;
    $v = trim((string)$v);
    return $v === '' ? null : $v;
}

function opt_int($body, $key) {
    $v = $body[$key] ?? null;
    return ($v === null || $v === '') ? null : (int)$v;
}

$memberId = (int)($body['member_id'] ?? 0);
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

// Aadhar/PAN must each belong to one real person -- reject if already used by
// another Member or Co_Borrower. Mobile gets a household exemption: it's
// normal for a co-borrower to share the member's own phone, so a match
// against this same MemberID's records doesn't count as a duplicate.
$dupFields = [
    ['AadharNo', opt_str($body, 'aadhar_no'), 'Aadhar number', false],
    ['MobileNo', opt_str($body, 'mobile'), 'Mobile number', true],
    ['PANNo', opt_str($body, 'pan_no'), 'PAN number', false],
];
foreach ($dupFields as [$column, $value, $label, $exemptHousehold]) {
    if ($value === null || $value === '') continue;
    if ($exemptHousehold) {
        $dupStmt = $pdo->prepare("
            SELECT 1 FROM Member WHERE $column = ? AND MemberID <> ?
            UNION ALL
            SELECT 1 FROM Co_Borrower WHERE $column = ? AND MemberID <> ?
        ");
        $dupStmt->execute([$value, $memberId, $value, $memberId]);
    } else {
        $dupStmt = $pdo->prepare("
            SELECT 1 FROM Member WHERE $column = ?
            UNION ALL
            SELECT 1 FROM Co_Borrower WHERE $column = ?
        ");
        $dupStmt->execute([$value, $value]);
    }
    if ($dupStmt->fetchColumn()) {
        json_response(false, null, "$label already exists", 409);
    }
}

try {
    $newId = insert_and_get_id($pdo, "INSERT INTO Co_Borrower
        (MemberID, FirstName, MiddleName, LastName, RelationToMember, Gender, DOB, MaritalStatus,
         MobileNo, OccupationID, MotherName, EducationID, ReligionID, CasteID, HouseTypeID,
         Address, VillageCity, Pincode, AadharNo, PANNo, VoterID, RationCardNo, DLNo, ElectricityBillNo)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)", [
        $memberId,
        opt_str($body, 'first_name'),
        opt_str($body, 'middle_name'),
        opt_str($body, 'last_name'),
        opt_str($body, 'relation_to_member'),
        opt_str($body, 'gender'),
        opt_str($body, 'dob'),
        opt_str($body, 'marital_status'),
        opt_str($body, 'mobile'),
        opt_int($body, 'occupation_id'),
        opt_str($body, 'mother_name'),
        opt_int($body, 'education_id'),
        opt_int($body, 'religion_id'),
        opt_int($body, 'caste_id'),
        opt_int($body, 'house_type_id'),
        opt_str($body, 'address'),
        opt_str($body, 'village_city'),
        opt_str($body, 'pincode'),
        opt_str($body, 'aadhar_no'),
        opt_str($body, 'pan_no'),
        opt_str($body, 'voter_id'),
        opt_str($body, 'ration_card_no'),
        opt_str($body, 'dl_no'),
        opt_str($body, 'electricity_bill_no'),
    ], 'CoBorrowerID');
} catch (Throwable $e) {
    error_log('coborrowers/create.php failed: ' . $e->getMessage());
    json_response(false, null, 'Failed to save co-borrower', 500);
}

json_response(true, [
    'coborrower_id' => $newId,
    'member_id'     => $memberId,
], 'Co-borrower saved');

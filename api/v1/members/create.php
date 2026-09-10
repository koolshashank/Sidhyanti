<?php
/**
 * POST /api/v1/members/create ("Client Sourcing" -- Borrower Enrollment)
 * Body: { first_name, middle_name?, last_name?, father_name?, mother_name?,
 *         marital_status?, dob?, mobile, education_id?, gender?, no_of_dependents?,
 *         occupation_id?, religion_id?, caste_id?, address?, state_id?, district_id?,
 *         pincode?, landmark?, house_type_id?, aadhar_no?, pan_no?, voter_id?,
 *         center_id?, lead_source_id? }
 *
 * Only first_name and mobile are truly required (matches the Member table's
 * NOT NULL columns -- everything else is nullable in the schema). This is
 * intentionally lenient during development so field staff/testers aren't
 * forced to fill every field on every test submission; tighten with proper
 * required-field UX once the form is finalized for production use.
 *
 * BranchID and AssignedToStaffID come from the caller's own JWT claims (a
 * field officer sources leads for their own branch/book, not an arbitrary
 * one) and CurrentStageID is always the "New Lead" stage. Co-borrower
 * details are captured separately, right after this succeeds, via
 * /api/v1/coborrowers/create -- not part of this request.
 */

require_once __DIR__ . '/../../includes/api_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();
$branchId = $claims['BranchID'] ?? null;
if (!$branchId) {
    json_response(false, null, 'No branch associated with your account', 422);
}

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

$required = ['first_name' => 'First name', 'mobile' => 'Mobile number'];
$missing = [];
foreach ($required as $key => $label) {
    if (opt_str($body, $key) === null) {
        $missing[] = $label;
    }
}
if ($missing) {
    json_response(false, null, implode(', ', $missing) . ' ' . (count($missing) > 1 ? 'are' : 'is') . ' required', 422);
}

// Aadhar/PAN/Mobile must each belong to one real person -- reject if any of
// them already exists on another Member or Co_Borrower record, so field
// staff can't (accidentally or otherwise) enroll the same person twice.
$dupFields = [
    ['AadharNo', opt_str($body, 'aadhar_no'), 'Aadhar number'],
    ['MobileNo', trim((string)$body['mobile']), 'Mobile number'],
    ['PANNo', opt_str($body, 'pan_no'), 'PAN number'],
];
foreach ($dupFields as [$column, $value, $label]) {
    if ($value === null || $value === '') continue;
    $dupStmt = $pdo->prepare("
        SELECT 1 FROM Member WHERE $column = ?
        UNION ALL
        SELECT 1 FROM Co_Borrower WHERE $column = ?
    ");
    $dupStmt->execute([$value, $value]);
    if ($dupStmt->fetchColumn()) {
        json_response(false, null, "$label already exists", 409);
    }
}

$centerId = opt_int($body, 'center_id');
if ($centerId !== null) {
    // A center picked by the client must actually belong to the caller's own branch.
    $check = $pdo->prepare("SELECT 1 FROM Center WHERE CenterID = ? AND BranchID = ?");
    $check->execute([$centerId, $branchId]);
    if (!$check->fetchColumn()) {
        json_response(false, null, 'Invalid center for your branch', 422);
    }
}

$newLeadStageId = $pdo->query("SELECT StageID FROM Lead_Stage_Master WHERE StageName = 'New Lead'")->fetchColumn();
$firstName = trim((string)$body['first_name']);
$lastName = opt_str($body, 'last_name') ?? '';
$gender = opt_str($body, 'gender') ?? 'Female';

try {
    $newId = insert_and_get_id($pdo, "INSERT INTO Member
        (BranchID, CenterID, FirstName, MiddleName, LastName, FatherHusbandName, MotherName, Gender, DOB,
         MaritalStatus, MobileNo, EducationID, NoOfDependents, OccupationID, ReligionID, CasteID,
         Address, StateID, DistrictID, Pincode, Landmark, HouseTypeID, AadharNo, PANNo, VoterID,
         LeadSourceID, AssignedToStaffID, CurrentStageID, CreatedBy)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)", [
        $branchId,
        $centerId,
        $firstName,
        opt_str($body, 'middle_name'),
        $lastName,
        opt_str($body, 'father_name'),
        opt_str($body, 'mother_name'),
        $gender,
        opt_str($body, 'dob'),
        opt_str($body, 'marital_status'),
        trim((string)$body['mobile']),
        opt_int($body, 'education_id'),
        opt_int($body, 'no_of_dependents'),
        opt_int($body, 'occupation_id'),
        opt_int($body, 'religion_id'),
        opt_int($body, 'caste_id'),
        opt_str($body, 'address'),
        opt_int($body, 'state_id'),
        opt_int($body, 'district_id'),
        opt_str($body, 'pincode'),
        opt_str($body, 'landmark'),
        opt_int($body, 'house_type_id'),
        opt_str($body, 'aadhar_no'),
        opt_str($body, 'pan_no'),
        opt_str($body, 'voter_id'),
        opt_int($body, 'lead_source_id'),
        (int)$claims['StaffID'],
        $newLeadStageId,
        (int)$claims['StaffID'],
    ], 'MemberID');
} catch (Throwable $e) {
    error_log('members/create.php failed: ' . $e->getMessage());
    json_response(false, null, 'Failed to save lead', 500);
}

json_response(true, [
    'member_id' => $newId,
    'full_name' => trim($firstName . ' ' . $lastName),
], 'Lead captured');

<?php
/**
 * GET /api/v1/members/detail?id={member_id}
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';
require_once __DIR__ . '/../../includes/reloan_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    json_response(false, null, 'id is required', 422);
}

$stmt = $pdo->prepare("SELECT v.*, mm.EnrollmentDate, mm.AssignedToStaffID, mm.BranchID FROM vw_Member_Full v JOIN Member mm ON v.MemberID = mm.MemberID WHERE v.MemberID = ?");
$stmt->execute([$id]);
$m = $stmt->fetch();
if (!$m) {
    json_response(false, null, 'Member not found', 404);
}
assert_member_access($m, $claims);

$loansStmt = $pdo->prepare("
  SELECT l.LoanID, l.LoanNo, l.LoanAmountSanctioned, l.LoanAmountApplied, l.ApplicationDate, l.DisbursementDate, stg.StageName
  FROM Loan l LEFT JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
  WHERE l.MemberID = ? ORDER BY l.LoanID DESC
");
$loansStmt->execute([$id]);
$loans = $loansStmt->fetchAll();

$outstandingStmt = $pdo->prepare("
  SELECT COALESCE(SUM(rs.PrincipalDue),0)
  FROM Repayment_Schedule rs JOIN Loan l ON rs.LoanID = l.LoanID
  WHERE l.MemberID = ? AND rs.IsPaid = 0
");
$outstandingStmt->execute([$id]);
$outstandingAmt = to_money($outstandingStmt->fetchColumn());

$photoStmt = $pdo->prepare("
  SELECT TOP 1 k.DocFilePath FROM Member_KYC_Document k
  JOIN KYC_Document_Type_Master dt ON k.DocTypeID = dt.DocTypeID
  WHERE k.MemberID = ? AND dt.DocTypeName = 'Client Photograph'
  ORDER BY k.UploadedAt DESC
");
$photoStmt->execute([$id]);
$photoUrl = $photoStmt->fetchColumn() ?: null;

// A member can only get a new loan proposal once every existing loan of
// theirs has reached a final stage (Closed/Rejected/Preclosed) -- mirrors
// the same guard enforced server-side in loans/create.php.
$openLoanStmt = $pdo->prepare("
  SELECT COUNT(*) FROM Loan l JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
  WHERE l.MemberID = ? AND stg.IsFinal = 0
");
$openLoanStmt->execute([$id]);
$canAddLoan = (int)$openLoanStmt->fetchColumn() === 0;

// Reloan-only: null for a fresh (first-time) borrower, or when no rule matches.
$reloanEligibility = $canAddLoan ? compute_reloan_eligibility($pdo, $id) : null;

json_response(true, [
    'member_id'           => (int)$m['MemberID'],
    'enrollment_no'       => $m['EnrollmentNo'],
    'full_name'           => trim($m['FirstName'] . ' ' . ($m['MiddleName'] ? $m['MiddleName'] . ' ' : '') . $m['LastName']),
    'father_husband_name' => $m['FatherHusbandName'],
    'gender'              => $m['Gender'],
    'dob'                 => to_iso_date($m['DOB']),
    'marital_status'      => $m['MaritalStatus'],
    'mobile_no'           => $m['MobileNo'],
    'address'             => $m['Address'],
    'village_city'        => $m['VillageCity'],
    'district_name'       => $m['DistrictName'],
    'state_name'          => $m['StateName'],
    'pincode'             => $m['Pincode'],
    'branch_name'         => $m['BranchName'],
    'center_name'         => $m['CenterName'],
    'current_stage'       => $m['CurrentStage'],
    'assigned_officer'    => $m['AssignedOfficer'],
    'is_active'           => to_bool($m['IsActive']),
    'enrollment_date'     => to_iso_date($m['EnrollmentDate']),
    'outstanding_principal' => $outstandingAmt,
    'occupation_name'     => $m['OccupationName'],
    'aadhar_no'           => $m['AadharNo'],
    'pan_no'              => $m['PANNo'],
    'lead_source'         => $m['LeadSource'],
    'photo_url'           => $photoUrl,
    'can_add_loan'        => $canAddLoan,
    'reloan_eligibility'  => $reloanEligibility === null ? null : [
        'previous_loan_no'     => $reloanEligibility['previous_loan_no'],
        'previous_loan_amount' => to_money($reloanEligibility['previous_loan_amount']),
        'cycle_number'         => $reloanEligibility['cycle_number'],
        'late_payments'        => $reloanEligibility['late_payments'],
        'rule_name'            => $reloanEligibility['rule_name'],
        'increase_percent'     => to_money($reloanEligibility['increase_percent']),
        'eligible_amount'      => to_money($reloanEligibility['eligible_amount']),
    ],
    'loans' => array_map(function ($l) {
        return [
            'loan_id'           => (int)$l['LoanID'],
            'loan_no'           => $l['LoanNo'],
            'amount'            => to_money($l['LoanAmountSanctioned'] ?: $l['LoanAmountApplied']),
            'stage'             => $l['StageName'],
            'application_date'  => to_iso_date($l['ApplicationDate']),
            'disbursement_date' => to_iso_date($l['DisbursementDate']),
        ];
    }, $loans),
]);

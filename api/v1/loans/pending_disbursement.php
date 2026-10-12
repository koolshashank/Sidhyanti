<?php
/**
 * GET /api/v1/loans/pending_disbursement.php
 * Branch-scoped list of loans currently at the 'Approved' stage (i.e.
 * ready to disburse but not yet disbursed).
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';
require_once __DIR__ . '/../../../includes/permission_helper.php';
require_once __DIR__ . '/../../../includes/loan_deduction_helper.php';
require_once __DIR__ . '/../../../includes/mask_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();

if (!role_has_permission($pdo, $claims['RoleID'] ?? null, 'disburse_loan')) {
    json_response(false, null, 'You do not have permission to disburse loans', 403);
}

[$whereScope, $scopeParams] = loan_scope_where($claims, 'l');
$where = $whereScope !== '' ? "AND $whereScope" : '';

$stmt = $pdo->prepare("
  SELECT l.LoanID, l.LoanNo, l.LoanAmountSanctioned, l.LoanAmountApplied, l.ApprovalDate, l.DisbursementPhotoPath,
         m.FirstName, m.LastName, c.CenterName, officer.FullName AS OfficerName,
         sch.ProcessingFeeAmount, sch.ProcessingFeeGSTPercent, sch.DocumentChargeAmount, sch.DocumentChargeGSTPercent,
         sch.OtherChargesAmount, sch.OtherChargesGSTPercent, sch.LoginFeeAmount, sch.LoginFeeGSTPercent, sch.InsuranceFeeAmount,
         bank.AccountNumber
  FROM Loan l
  JOIN Member m ON l.MemberID = m.MemberID
  LEFT JOIN Center c ON l.CenterID = c.CenterID
  LEFT JOIN Staff officer ON l.LoanOfficerStaffID = officer.StaffID
  LEFT JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
  LEFT JOIN Loan_Scheme sch ON l.SchemeID = sch.SchemeID
  LEFT JOIN Member_Bank_Details bank ON bank.MemberID = l.MemberID
  WHERE stg.StageName = 'Approved' $where
  ORDER BY l.ApprovalDate ASC
");
$stmt->execute($scopeParams);
$rows = $stmt->fetchAll();

json_response(true, [
    'items' => array_map(function ($r) {
        $loanAmount = (float)to_money($r['LoanAmountSanctioned'] ?: $r['LoanAmountApplied']);
        $deductions = loan_scheme_deductions($r);
        $accountNo = trim((string)($r['AccountNumber'] ?? ''));
        return [
            'loan_id'        => (int)$r['LoanID'],
            'loan_no'        => $r['LoanNo'],
            'member_name'    => trim($r['FirstName'] . ' ' . $r['LastName']),
            'center_name'    => $r['CenterName'],
            'officer_name'   => $r['OfficerName'],
            'loan_amount'    => $loanAmount,
            'approval_date'  => to_iso_date($r['ApprovalDate']),
            'photo_captured' => !empty($r['DisbursementPhotoPath']),
            'photo_url'      => $r['DisbursementPhotoPath'] ?: null,
            'processing_fee'   => $deductions['processing_fee'],
            'insurance_amount' => $deductions['insurance_amount'],
            'other_charges'    => $deductions['other_charges'],
            'total_deductions' => $deductions['total_deductions'],
            'net_disbursement_amount' => round($loanAmount - $deductions['total_deductions'], 2),
            'bank_account_masked' => $accountNo === '' ? null : mask_account_number($accountNo),
        ];
    }, $rows),
]);

<?php
/**
 * Key Fact Sheet (KFS) -- the RBI-mandated pre-disbursement disclosure for
 * a loan. Computed live from Loan/Loan_Scheme every time it's viewed
 * (nothing about the figures themselves is persisted), using the exact
 * same flat-EMI math as includes/loan_schedule_helper.php so the KFS
 * always matches what actually gets disbursed. Shared by the web print
 * page (kfs_view.php) and the mobile API (api/v1/loans/kfs.php).
 */
require_once __DIR__ . '/loan_deduction_helper.php';

function get_company_profile(PDO $pdo): array {
    $row = $pdo->query("SELECT * FROM Company_Profile WHERE ProfileID = 1")->fetch();
    return $row ?: [];
}

/**
 * Solves for the monthly IRR of the loan's cash flows (t0: -net amount
 * actually in the borrower's hand after all fees are deducted; t1..tenure:
 * +EMI each month) via bisection -- NPV(r) is monotonically decreasing in
 * r, so bisection can't diverge the way Newton-Raphson sometimes does.
 * Returns the effective ANNUAL rate as a percentage (the "APR" RBI's KFS
 * format calls for), or null if the inputs don't describe a real loan.
 */
function solve_kfs_apr(float $netDisbursedAmount, float $emi, int $tenureMonths): ?float {
    if ($netDisbursedAmount <= 0 || $emi <= 0 || $tenureMonths <= 0) {
        return null;
    }

    $npv = function (float $monthlyRate) use ($netDisbursedAmount, $emi, $tenureMonths): float {
        $pv = 0.0;
        for ($i = 1; $i <= $tenureMonths; $i++) {
            $pv += $emi / (1 + $monthlyRate) ** $i;
        }
        return $pv - $netDisbursedAmount;
    };

    $low = 0.0;
    $high = 2.0; // 200%/month ceiling -- comfortably above any real microfinance rate
    if ($npv($low) < 0) {
        // Even at 0% interest the stated EMI/tenure doesn't recover the
        // disbursed amount (shouldn't happen for a real loan) -- bail out
        // rather than report a meaningless negative APR.
        return null;
    }

    for ($i = 0; $i < 100; $i++) {
        $mid = ($low + $high) / 2;
        if ($npv($mid) > 0) {
            $low = $mid;
        } else {
            $high = $mid;
        }
    }

    $monthlyRate = ($low + $high) / 2;
    return round((((1 + $monthlyRate) ** 12) - 1) * 100, 2);
}

/** Full KFS payload for a loan, or null if the loan doesn't exist. */
function build_kfs_data(PDO $pdo, int $loanId): ?array {
    $stmt = $pdo->prepare("
      SELECT l.*, m.FirstName, m.LastName, m.FatherHusbandName, m.Address, m.VillageCity, m.MobileNo, m.EnrollmentNo,
             b.BranchName, b.Address AS BranchAddress, b.Phone AS BranchPhone,
             c.CenterName, p.PurposeName, stg.StageName,
             sch.ProcessingFeeAmount, sch.ProcessingFeeGSTPercent, sch.DocumentChargeAmount, sch.DocumentChargeGSTPercent,
             sch.OtherChargesAmount, sch.OtherChargesGSTPercent, sch.LoginFeeAmount, sch.LoginFeeGSTPercent,
             sch.InsuranceFeeAmount, sch.InsurancePeriodMonths, sch.SumAssuredClient, sch.SchemeName
      FROM Loan l
      JOIN Member m ON l.MemberID = m.MemberID
      LEFT JOIN Branch b ON l.BranchID = b.BranchID
      LEFT JOIN Center c ON l.CenterID = c.CenterID
      LEFT JOIN Loan_Purpose_Master p ON l.PurposeID = p.PurposeID
      LEFT JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
      LEFT JOIN Loan_Scheme sch ON l.SchemeID = sch.SchemeID
      WHERE l.LoanID = ?
    ");
    $stmt->execute([$loanId]);
    $l = $stmt->fetch();
    if (!$l) {
        return null;
    }

    $amount = (float)($l['LoanAmountSanctioned'] ?: $l['LoanAmountApplied']);
    $tenure = (int)$l['TenureMonths'];
    $rate = (float)$l['InterestRatePercent'];

    $principalPerInst = $tenure > 0 ? round($amount / $tenure, 2) : 0.0;
    $interestPerInst = round(($amount * $rate / 100) / 12, 2);
    $emi = $principalPerInst + $interestPerInst;

    $deductions = loan_scheme_deductions($l);
    $netDisbursedAmount = round($amount - $deductions['total_deductions'], 2);

    $totalInterestPayable = round($interestPerInst * $tenure, 2);
    $totalAmountPayable = round($emi * $tenure, 2);

    $schedule = [];
    for ($i = 1; $i <= $tenure; $i++) {
        $schedule[] = [
            'installment_no' => $i,
            'principal'      => $principalPerInst,
            'interest'       => $interestPerInst,
            'emi'            => $emi,
        ];
    }

    $company = get_company_profile($pdo);

    return [
        'loan_id'               => (int)$l['LoanID'],
        'loan_no'                => $l['LoanNo'] ?? ('L' . str_pad((string)$l['LoanID'], 6, '0', STR_PAD_LEFT)),
        'stage'                  => $l['StageName'],
        'generated_at'           => date('c'),
        'member_name'            => trim(($l['FirstName'] ?? '') . ' ' . ($l['LastName'] ?? '')),
        'father_husband_name'    => $l['FatherHusbandName'],
        'member_address'         => trim(($l['Address'] ?? '') . ', ' . ($l['VillageCity'] ?? ''), ', '),
        'member_mobile'          => $l['MobileNo'],
        'enrollment_no'          => $l['EnrollmentNo'],
        'branch_name'            => $l['BranchName'],
        'branch_address'         => $l['BranchAddress'],
        'branch_phone'           => $l['BranchPhone'],
        'center_name'            => $l['CenterName'],
        'purpose_name'           => $l['PurposeName'],
        'scheme_name'            => $l['SchemeName'],
        'loan_amount_sanctioned' => $amount,
        'tenure_months'          => $tenure,
        'interest_rate_percent'  => $rate,
        'interest_method'        => 'Flat',
        'repayment_frequency'    => 'Monthly',
        'processing_fee'         => $deductions['processing_fee'],
        'insurance_amount'       => $deductions['insurance_amount'],
        'other_charges'          => $deductions['other_charges'],
        'total_deductions'       => $deductions['total_deductions'],
        'net_disbursed_amount'   => $netDisbursedAmount,
        'emi_amount'             => $emi,
        'total_interest_payable' => $totalInterestPayable,
        'total_amount_payable'   => $totalAmountPayable,
        'apr_percent'            => solve_kfs_apr($netDisbursedAmount, $emi, $tenure),
        'cooling_off_period_days' => (int)($company['CoolingOffPeriodDays'] ?? 3),
        'schedule'               => $schedule,
        'kfs_signed_collected'   => (bool)($l['KFSSignedCollected'] ?? 0),
        'kfs_signed_at'          => $l['KFSSignedAt'] ?? null,
        'company'                => [
            'company_name'              => $company['CompanyName'] ?? '[Company Name]',
            'nbfc_registration_no'      => $company['NBFCRegistrationNo'] ?? '',
            'registered_office_address' => $company['RegisteredOfficeAddress'] ?? '',
            'customer_care_phone'       => $company['CustomerCarePhone'] ?? '',
            'customer_care_email'       => $company['CustomerCareEmail'] ?? '',
            'grievance_officer_name'    => $company['GrievanceOfficerName'] ?? '',
            'grievance_officer_phone'   => $company['GrievanceOfficerPhone'] ?? '',
        ],
    ];
}

class KfsScheduleRow {
  final int installmentNo;
  final double principal;
  final double interest;
  final double emi;

  KfsScheduleRow({required this.installmentNo, required this.principal, required this.interest, required this.emi});

  factory KfsScheduleRow.fromJson(Map<String, dynamic> json) => KfsScheduleRow(
        installmentNo: json['installment_no'] as int,
        principal: (json['principal'] as num?)?.toDouble() ?? 0,
        interest: (json['interest'] as num?)?.toDouble() ?? 0,
        emi: (json['emi'] as num?)?.toDouble() ?? 0,
      );
}

class KfsCompanyInfo {
  final String companyName;
  final String nbfcRegistrationNo;
  final String registeredOfficeAddress;
  final String customerCarePhone;
  final String customerCareEmail;
  final String grievanceOfficerName;
  final String grievanceOfficerPhone;

  KfsCompanyInfo({
    required this.companyName,
    required this.nbfcRegistrationNo,
    required this.registeredOfficeAddress,
    required this.customerCarePhone,
    required this.customerCareEmail,
    required this.grievanceOfficerName,
    required this.grievanceOfficerPhone,
  });

  factory KfsCompanyInfo.fromJson(Map<String, dynamic> json) => KfsCompanyInfo(
        companyName: json['company_name'] as String? ?? '',
        nbfcRegistrationNo: json['nbfc_registration_no'] as String? ?? '',
        registeredOfficeAddress: json['registered_office_address'] as String? ?? '',
        customerCarePhone: json['customer_care_phone'] as String? ?? '',
        customerCareEmail: json['customer_care_email'] as String? ?? '',
        grievanceOfficerName: json['grievance_officer_name'] as String? ?? '',
        grievanceOfficerPhone: json['grievance_officer_phone'] as String? ?? '',
      );
}

/// The RBI Key Fact Sheet for a loan -- see api/v1/loans/kfs.php /
/// includes/kfs_helper.php. Computed live, not stored, except for the
/// signed-and-collected flag.
class KfsData {
  final int loanId;
  final String loanNo;
  final String? stage;
  final String memberName;
  final String? fatherHusbandName;
  final String memberAddress;
  final String? memberMobile;
  final String? enrollmentNo;
  final String? branchName;
  final String? centerName;
  final String? purposeName;
  final String? schemeName;
  final double loanAmountSanctioned;
  final int tenureMonths;
  final double interestRatePercent;
  final String repaymentFrequency;
  final double processingFee;
  final double insuranceAmount;
  final double otherCharges;
  final double totalDeductions;
  final double netDisbursedAmount;
  final double emiAmount;
  final double totalInterestPayable;
  final double totalAmountPayable;
  final double? aprPercent;
  final int coolingOffPeriodDays;
  final List<KfsScheduleRow> schedule;
  final bool kfsSignedCollected;
  final DateTime? kfsSignedAt;
  final KfsCompanyInfo company;

  KfsData({
    required this.loanId,
    required this.loanNo,
    required this.stage,
    required this.memberName,
    required this.fatherHusbandName,
    required this.memberAddress,
    required this.memberMobile,
    required this.enrollmentNo,
    required this.branchName,
    required this.centerName,
    required this.purposeName,
    required this.schemeName,
    required this.loanAmountSanctioned,
    required this.tenureMonths,
    required this.interestRatePercent,
    required this.repaymentFrequency,
    required this.processingFee,
    required this.insuranceAmount,
    required this.otherCharges,
    required this.totalDeductions,
    required this.netDisbursedAmount,
    required this.emiAmount,
    required this.totalInterestPayable,
    required this.totalAmountPayable,
    required this.aprPercent,
    required this.coolingOffPeriodDays,
    required this.schedule,
    required this.kfsSignedCollected,
    required this.kfsSignedAt,
    required this.company,
  });

  factory KfsData.fromJson(Map<String, dynamic> json) => KfsData(
        loanId: json['loan_id'] as int,
        loanNo: json['loan_no'] as String? ?? '',
        stage: json['stage'] as String?,
        memberName: json['member_name'] as String? ?? '',
        fatherHusbandName: json['father_husband_name'] as String?,
        memberAddress: json['member_address'] as String? ?? '',
        memberMobile: json['member_mobile'] as String?,
        enrollmentNo: json['enrollment_no'] as String?,
        branchName: json['branch_name'] as String?,
        centerName: json['center_name'] as String?,
        purposeName: json['purpose_name'] as String?,
        schemeName: json['scheme_name'] as String?,
        loanAmountSanctioned: (json['loan_amount_sanctioned'] as num?)?.toDouble() ?? 0,
        tenureMonths: json['tenure_months'] as int? ?? 0,
        interestRatePercent: (json['interest_rate_percent'] as num?)?.toDouble() ?? 0,
        repaymentFrequency: json['repayment_frequency'] as String? ?? 'Monthly',
        processingFee: (json['processing_fee'] as num?)?.toDouble() ?? 0,
        insuranceAmount: (json['insurance_amount'] as num?)?.toDouble() ?? 0,
        otherCharges: (json['other_charges'] as num?)?.toDouble() ?? 0,
        totalDeductions: (json['total_deductions'] as num?)?.toDouble() ?? 0,
        netDisbursedAmount: (json['net_disbursed_amount'] as num?)?.toDouble() ?? 0,
        emiAmount: (json['emi_amount'] as num?)?.toDouble() ?? 0,
        totalInterestPayable: (json['total_interest_payable'] as num?)?.toDouble() ?? 0,
        totalAmountPayable: (json['total_amount_payable'] as num?)?.toDouble() ?? 0,
        aprPercent: (json['apr_percent'] as num?)?.toDouble(),
        coolingOffPeriodDays: json['cooling_off_period_days'] as int? ?? 3,
        schedule: (json['schedule'] as List<dynamic>? ?? []).map((e) => KfsScheduleRow.fromJson(e as Map<String, dynamic>)).toList(),
        kfsSignedCollected: json['kfs_signed_collected'] as bool? ?? false,
        kfsSignedAt: json['kfs_signed_at'] != null ? DateTime.tryParse(json['kfs_signed_at'] as String) : null,
        company: KfsCompanyInfo.fromJson(json['company'] as Map<String, dynamic>? ?? {}),
      );
}

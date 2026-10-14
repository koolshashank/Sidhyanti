/// Full editable detail for a pending loan Proposal (api/v1/loans/proposal_detail.php)
/// -- shown on the "Review Loan Proposal" screen a Branch/Area Manager opens
/// from their approval queue, so they can see everything the CM captured
/// and correct it before approving/rejecting.
class ProposalReviewDetail {
  final int loanId;
  final String loanNo;
  final int? loanCycle;
  final int? loanApprovalId;
  final String? approvalStatus;
  final String? branchName;
  final String? centerName;
  final String? enrollmentNo;
  final String borrowerName;
  final String? coBorrowerName;
  final int memberId;
  final String? memberFirstName;
  final String? memberLastName;
  final String? fatherHusbandName;
  final String? memberMobileNo;
  final String? memberAddress;
  final String? memberVillageCity;
  final int? coBorrowerId;
  final String? coBorrowerFirstName;
  final String? coBorrowerLastName;
  final String? coBorrowerAddress;
  final String? coBorrowerVillageCity;
  final int? schemeId;
  final double loanAmount;
  final int? purposeId;
  final double emiAmount;
  final double? selfIncome;
  final double? husbandSonIncome;
  final double? otherIncome;
  final double? residentIncome;
  final double? houseExpenses;
  final double? mfiExpenses;
  final double? businessExpenses;
  final String? sourceOfIncome;
  final String? ifscCode;
  final String? bankName;
  final String? bankBranch;
  final String? bankAddress;
  final String? accountNumber;
  final String? beneficiaryName;
  final DateTime? applicationDate;
  final String? grtStatus;
  final DateTime? grtDate;
  final String? grtPhotoUrl;
  final String? memberPhotoUrl;
  final String? coBorrowerPhotoUrl;

  bool get grtPassed => grtStatus == 'Pass';

  ProposalReviewDetail({
    required this.loanId,
    required this.loanNo,
    required this.loanCycle,
    required this.loanApprovalId,
    required this.approvalStatus,
    required this.branchName,
    required this.centerName,
    required this.enrollmentNo,
    required this.borrowerName,
    required this.coBorrowerName,
    required this.memberId,
    required this.memberFirstName,
    required this.memberLastName,
    required this.fatherHusbandName,
    required this.memberMobileNo,
    required this.memberAddress,
    required this.memberVillageCity,
    required this.coBorrowerId,
    required this.coBorrowerFirstName,
    required this.coBorrowerLastName,
    required this.coBorrowerAddress,
    required this.coBorrowerVillageCity,
    required this.schemeId,
    required this.loanAmount,
    required this.purposeId,
    required this.emiAmount,
    required this.selfIncome,
    required this.husbandSonIncome,
    required this.otherIncome,
    required this.residentIncome,
    required this.houseExpenses,
    required this.mfiExpenses,
    required this.businessExpenses,
    required this.sourceOfIncome,
    required this.ifscCode,
    required this.bankName,
    required this.bankBranch,
    required this.bankAddress,
    required this.accountNumber,
    required this.beneficiaryName,
    required this.applicationDate,
    required this.grtStatus,
    required this.grtDate,
    required this.grtPhotoUrl,
    required this.memberPhotoUrl,
    required this.coBorrowerPhotoUrl,
  });

  factory ProposalReviewDetail.fromJson(Map<String, dynamic> json) => ProposalReviewDetail(
        loanId: json['loan_id'] as int,
        loanNo: json['loan_no'] as String? ?? '',
        loanCycle: json['loan_cycle'] as int?,
        loanApprovalId: json['loan_approval_id'] as int?,
        approvalStatus: json['approval_status'] as String?,
        branchName: json['branch_name'] as String?,
        centerName: json['center_name'] as String?,
        enrollmentNo: json['enrollment_no'] as String?,
        borrowerName: json['borrower_name'] as String? ?? '',
        coBorrowerName: json['co_borrower_name'] as String?,
        memberId: json['member_id'] as int? ?? 0,
        memberFirstName: json['member_first_name'] as String?,
        memberLastName: json['member_last_name'] as String?,
        fatherHusbandName: json['father_husband_name'] as String?,
        memberMobileNo: json['member_mobile_no'] as String?,
        memberAddress: json['member_address'] as String?,
        memberVillageCity: json['member_village_city'] as String?,
        coBorrowerId: json['co_borrower_id'] as int?,
        coBorrowerFirstName: json['co_borrower_first_name'] as String?,
        coBorrowerLastName: json['co_borrower_last_name'] as String?,
        coBorrowerAddress: json['co_borrower_address'] as String?,
        coBorrowerVillageCity: json['co_borrower_village_city'] as String?,
        schemeId: json['scheme_id'] as int?,
        loanAmount: (json['loan_amount'] as num?)?.toDouble() ?? 0,
        purposeId: json['purpose_id'] as int?,
        emiAmount: (json['emi_amount'] as num?)?.toDouble() ?? 0,
        selfIncome: (json['self_income'] as num?)?.toDouble(),
        husbandSonIncome: (json['husband_son_income'] as num?)?.toDouble(),
        otherIncome: (json['other_income'] as num?)?.toDouble(),
        residentIncome: (json['resident_income'] as num?)?.toDouble(),
        houseExpenses: (json['house_expenses'] as num?)?.toDouble(),
        mfiExpenses: (json['mfi_expenses'] as num?)?.toDouble(),
        businessExpenses: (json['business_expenses'] as num?)?.toDouble(),
        sourceOfIncome: json['source_of_income'] as String?,
        ifscCode: json['ifsc_code'] as String?,
        bankName: json['bank_name'] as String?,
        bankBranch: json['bank_branch'] as String?,
        bankAddress: json['bank_address'] as String?,
        accountNumber: json['account_number'] as String?,
        beneficiaryName: json['beneficiary_name'] as String?,
        applicationDate: json['application_date'] != null ? DateTime.tryParse(json['application_date'] as String) : null,
        grtStatus: json['grt_status'] as String?,
        grtDate: json['grt_date'] != null ? DateTime.tryParse(json['grt_date'] as String) : null,
        grtPhotoUrl: json['grt_photo_url'] as String?,
        memberPhotoUrl: json['member_photo_url'] as String?,
        coBorrowerPhotoUrl: json['co_borrower_photo_url'] as String?,
      );
}

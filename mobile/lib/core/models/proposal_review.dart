/// Full editable detail for a pending loan Proposal (api/v1/loans/proposal_detail.php)
/// -- shown on the "Review Loan Proposal" screen a Branch/Area Manager opens
/// from their approval queue, so they can see everything the CM captured
/// and correct it before approving/rejecting.
class ProposalReviewDetail {
  final int loanId;
  final String loanNo;
  final int? loanApprovalId;
  final String? approvalStatus;
  final String? branchName;
  final String? centerName;
  final String? enrollmentNo;
  final String borrowerName;
  final String? coBorrowerName;
  final int? schemeId;
  final double loanAmount;
  final int? purposeId;
  final double emiAmount;
  final double? selfIncome;
  final double? husbandSonIncome;
  final double? otherIncome;
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

  ProposalReviewDetail({
    required this.loanId,
    required this.loanNo,
    required this.loanApprovalId,
    required this.approvalStatus,
    required this.branchName,
    required this.centerName,
    required this.enrollmentNo,
    required this.borrowerName,
    required this.coBorrowerName,
    required this.schemeId,
    required this.loanAmount,
    required this.purposeId,
    required this.emiAmount,
    required this.selfIncome,
    required this.husbandSonIncome,
    required this.otherIncome,
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
  });

  factory ProposalReviewDetail.fromJson(Map<String, dynamic> json) => ProposalReviewDetail(
        loanId: json['loan_id'] as int,
        loanNo: json['loan_no'] as String? ?? '',
        loanApprovalId: json['loan_approval_id'] as int?,
        approvalStatus: json['approval_status'] as String?,
        branchName: json['branch_name'] as String?,
        centerName: json['center_name'] as String?,
        enrollmentNo: json['enrollment_no'] as String?,
        borrowerName: json['borrower_name'] as String? ?? '',
        coBorrowerName: json['co_borrower_name'] as String?,
        schemeId: json['scheme_id'] as int?,
        loanAmount: (json['loan_amount'] as num?)?.toDouble() ?? 0,
        purposeId: json['purpose_id'] as int?,
        emiAmount: (json['emi_amount'] as num?)?.toDouble() ?? 0,
        selfIncome: (json['self_income'] as num?)?.toDouble(),
        husbandSonIncome: (json['husband_son_income'] as num?)?.toDouble(),
        otherIncome: (json['other_income'] as num?)?.toDouble(),
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
      );
}

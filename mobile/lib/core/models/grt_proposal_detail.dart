/// Read-only loan-proposal summary shown on the standalone "Conduct GRT"
/// screen (api/v1/loans/grt_proposal_detail.php) -- everything the field
/// staff captured (applicant, loan, income, bank details) before the GRT
/// form itself.
class GrtProposalDetail {
  final int loanId;
  final String loanNo;
  final String? branchName;
  final String? centerName;
  final String? enrollmentNo;
  final String borrowerName;
  final String? coBorrowerName;
  final String? schemeName;
  final double loanAmount;
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

  double get totalIncome => (selfIncome ?? 0) + (husbandSonIncome ?? 0) + (otherIncome ?? 0) + (residentIncome ?? 0);
  double get totalExpenses => (houseExpenses ?? 0) + (mfiExpenses ?? 0) + (businessExpenses ?? 0);
  double get disposableIncome => totalIncome - totalExpenses;

  GrtProposalDetail({
    required this.loanId,
    required this.loanNo,
    required this.branchName,
    required this.centerName,
    required this.enrollmentNo,
    required this.borrowerName,
    required this.coBorrowerName,
    required this.schemeName,
    required this.loanAmount,
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
  });

  factory GrtProposalDetail.fromJson(Map<String, dynamic> json) => GrtProposalDetail(
        loanId: json['loan_id'] as int,
        loanNo: json['loan_no'] as String? ?? '',
        branchName: json['branch_name'] as String?,
        centerName: json['center_name'] as String?,
        enrollmentNo: json['enrollment_no'] as String?,
        borrowerName: json['borrower_name'] as String? ?? '',
        coBorrowerName: json['co_borrower_name'] as String?,
        schemeName: json['scheme_name'] as String?,
        loanAmount: (json['loan_amount'] as num?)?.toDouble() ?? 0,
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
      );
}

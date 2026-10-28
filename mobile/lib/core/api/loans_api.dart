import 'dart:io';

import 'package:dio/dio.dart';

import '../models/approval.dart';
import '../models/grt_proposal_detail.dart';
import '../models/kfs.dart';
import '../models/loan.dart';
import '../models/proposal_review.dart';
import '../models/reloan_candidate.dart';
import '../models/tvr.dart';
import 'api_client.dart';
import 'endpoints.dart';

class LoanScheme {
  final int id;
  final String name;
  final int tenureMonths;
  final double defaultAmount;
  final double interestRatePercent;

  /// The scheme's configured per-installment amount (Loan_Scheme.
  /// InstallmentAmountSlot1 on the web CRM's Scheme edit page) -- null until
  /// an admin fills it in for that scheme, in which case the EMI field is
  /// left for the field officer to enter manually, same as before.
  final double? emiAmount;

  /// Groups schemes for the Product Type -> Product two-step picker on the
  /// Loan Proposal screen (e.g. "JLG", "BL", "MSME"). Null for a scheme with
  /// no ProductTypeID set.
  final int? productTypeId;
  final String? productTypeName;

  LoanScheme({
    required this.id,
    required this.name,
    required this.tenureMonths,
    required this.defaultAmount,
    required this.interestRatePercent,
    required this.emiAmount,
    required this.productTypeId,
    required this.productTypeName,
  });

  factory LoanScheme.fromJson(Map<String, dynamic> json) => LoanScheme(
        id: json['id'] as int,
        name: json['name'] as String? ?? '',
        tenureMonths: json['tenure_months'] as int? ?? 0,
        defaultAmount: (json['default_amount'] as num?)?.toDouble() ?? 0,
        interestRatePercent: (json['interest_rate_percent'] as num?)?.toDouble() ?? 0,
        emiAmount: (json['emi_amount'] as num?)?.toDouble(),
        productTypeId: json['product_type_id'] as int?,
        productTypeName: json['product_type_name'] as String?,
      );
}

class LoanPurpose {
  final int id;
  final String name;

  LoanPurpose({required this.id, required this.name});

  factory LoanPurpose.fromJson(Map<String, dynamic> json) =>
      LoanPurpose(id: json['id'] as int, name: json['name'] as String? ?? '');
}

class LoanLookups {
  final List<LoanScheme> schemes;
  final List<LoanPurpose> purposes;

  LoanLookups({required this.schemes, required this.purposes});
}

class NewLoanResult {
  final int loanId;
  final int assessmentId;

  NewLoanResult({required this.loanId, required this.assessmentId});

  factory NewLoanResult.fromJson(Map<String, dynamic> json) => NewLoanResult(
        loanId: json['loan_id'] as int,
        assessmentId: json['assessment_id'] as int,
      );
}

class LoansApi {
  final ApiClient _client;

  LoansApi(this._client);

  Future<LoanDetail> detail(int loanId) async {
    final data = await _client.get(Endpoints.loansDetail, query: {'id': loanId});
    return LoanDetail.fromJson(data);
  }

  Future<KfsData> kfs(int loanId) async {
    final data = await _client.get(Endpoints.loansKfs, query: {'loan_id': loanId});
    return KfsData.fromJson(data);
  }

  Future<void> markKfsSigned(int loanId) async {
    await _client.post(Endpoints.loansKfsMarkSigned, data: {'loan_id': loanId});
  }

  Future<void> requestWriteOff({required int loanId, String? reason}) async {
    await _client.post(Endpoints.loansWriteOffRequest, data: {
      'loan_id': loanId,
      if (reason != null && reason.isNotEmpty) 'reason': reason,
    });
  }

  Future<List<OverdueInstallment>> overdueInstallments(int loanId) async {
    final data = await _client.get(Endpoints.loansOverdueInstallments, query: {'loan_id': loanId});
    return (data['items'] as List<dynamic>).map((e) => OverdueInstallment.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<LoanLookups> lookups() async {
    final data = await _client.get(Endpoints.loansLookups);
    return LoanLookups(
      schemes: (data['schemes'] as List<dynamic>).map((e) => LoanScheme.fromJson(e as Map<String, dynamic>)).toList(),
      purposes: (data['purposes'] as List<dynamic>).map((e) => LoanPurpose.fromJson(e as Map<String, dynamic>)).toList(),
    );
  }

  /// Only [memberId] and [schemeId] are required -- schemeId (the chosen
  /// Product) is what supplies the Loan table's NOT NULL amount/tenure/
  /// interest-rate columns. Everything else is optional.
  Future<NewLoanResult> createProposal({
    required int memberId,
    required int schemeId,
    double? amount,
    int? purposeId,
    double? emiAmount,
    double? selfIncome,
    double? husbandSonIncome,
    double? otherIncome,
    double? residentIncome,
    double? houseExpenses,
    double? mfiExpenses,
    double? businessExpenses,
    String? sourceOfIncome,
    String? ifscCode,
    String? bankName,
    String? bankBranch,
    String? bankAddress,
    String? accountNumber,
    String? beneficiaryName,
  }) async {
    final data = await _client.post(Endpoints.loansCreate, data: {
      'member_id': memberId,
      'scheme_id': schemeId,
      'amount': ?amount,
      'purpose_id': ?purposeId,
      'emi_amount': ?emiAmount,
      'self_income': ?selfIncome,
      'husband_son_income': ?husbandSonIncome,
      'other_income': ?otherIncome,
      'resident_income': ?residentIncome,
      'house_expenses': ?houseExpenses,
      'mfi_expenses': ?mfiExpenses,
      'business_expenses': ?businessExpenses,
      if (sourceOfIncome != null && sourceOfIncome.isNotEmpty) 'source_of_income': sourceOfIncome,
      if (ifscCode != null && ifscCode.isNotEmpty) 'ifsc_code': ifscCode,
      if (bankName != null && bankName.isNotEmpty) 'bank_name': bankName,
      if (bankBranch != null && bankBranch.isNotEmpty) 'bank_branch': bankBranch,
      if (bankAddress != null && bankAddress.isNotEmpty) 'bank_address': bankAddress,
      if (accountNumber != null && accountNumber.isNotEmpty) 'account_number': accountNumber,
      if (beneficiaryName != null && beneficiaryName.isNotEmpty) 'beneficiary_name': beneficiaryName,
    });
    return NewLoanResult.fromJson(data);
  }

  Future<void> changeScheme({required int loanId, required int newSchemeId}) async {
    await _client.post(Endpoints.loansChangeScheme, data: {
      'loan_id': loanId,
      'new_scheme_id': newSchemeId,
    });
  }

  Future<void> conductGrt({
    required int loanId,
    required bool passed,
    required DateTime grtDate,
    double? financeAmountVerified,
    String? remarks,
    File? documentFile,
  }) async {
    final iso = '${grtDate.year.toString().padLeft(4, '0')}-${grtDate.month.toString().padLeft(2, '0')}-${grtDate.day.toString().padLeft(2, '0')}';
    final formData = FormData.fromMap({
      'loan_id': loanId,
      'status': passed ? 'Pass' : 'Fail',
      'grt_date': iso,
      'finance_amount_verified': ?financeAmountVerified,
      if (remarks != null && remarks.isNotEmpty) 'remarks': remarks,
      if (documentFile != null) 'document': await MultipartFile.fromFile(documentFile.path, filename: documentFile.path.split('/').last),
    });
    await _client.postMultipart(Endpoints.loansGrtCreate, formData);
  }

  /// Opens a Branch-Manager preclose sign-off request for [loanId] (gated by
  /// the "request_loan_preclose" permission; see LoanDetail.canRequestPreclose).
  Future<void> requestPreclose({required int loanId, String? remarks}) async {
    await _client.post(Endpoints.loansPrecloseRequest, data: {
      'loan_id': loanId,
      if (remarks != null && remarks.isNotEmpty) 'remarks': remarks,
    });
  }

  /// Reviewer-facing queue of pending Loan_Approval rows -- [type] is
  /// 'Proposal' or 'Preclose'.
  Future<List<LoanApprovalItem>> pendingApprovals(String type) async {
    final data = await _client.get(Endpoints.loansPendingApprovals, query: {'type': type});
    return (data['items'] as List<dynamic>).map((e) => LoanApprovalItem.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<void> reviewLoanApproval({
    required int loanApprovalId,
    required bool approve,
    String? remarks,
  }) async {
    await _client.post(Endpoints.loansApprovalReview, data: {
      'loan_approval_id': loanApprovalId,
      'action': approve ? 'approve' : 'reject',
      if (remarks != null && remarks.isNotEmpty) 'remarks': remarks,
    });
  }

  /// Full editable detail for a pending Proposal -- the reviewer's "Review
  /// Loan Proposal" screen.
  Future<ProposalReviewDetail> proposalReviewDetail(int loanId) async {
    final data = await _client.get(Endpoints.loansProposalDetail, query: {'loan_id': loanId});
    return ProposalReviewDetail.fromJson(data);
  }

  /// Read-only proposal summary for the standalone "Conduct GRT" screen.
  Future<GrtProposalDetail> grtProposalDetail(int loanId) async {
    final data = await _client.get(Endpoints.loansGrtProposalDetail, query: {'loan_id': loanId});
    return GrtProposalDetail.fromJson(data);
  }

  /// Saves a reviewer's corrections to a proposal still under review --
  /// product/amount/purpose/EMI, income assessment, and bank details --
  /// before they approve/reject it via [reviewLoanApproval].
  Future<void> updateProposal({
    required int loanId,
    required int schemeId,
    required double amount,
    int? purposeId,
    double? emiAmount,
    double? selfIncome,
    double? husbandSonIncome,
    double? otherIncome,
    double? residentIncome,
    double? houseExpenses,
    double? mfiExpenses,
    double? businessExpenses,
    String? sourceOfIncome,
    String? ifscCode,
    String? bankName,
    String? bankBranch,
    String? bankAddress,
    String? accountNumber,
    String? beneficiaryName,
    String? memberFirstName,
    String? memberLastName,
    String? fatherHusbandName,
    String? memberMobileNo,
    String? memberAddress,
    String? memberVillageCity,
    String? coBorrowerFirstName,
    String? coBorrowerLastName,
    String? coBorrowerAddress,
    String? coBorrowerVillageCity,
  }) async {
    await _client.post(Endpoints.loansProposalUpdate, data: {
      'loan_id': loanId,
      'scheme_id': schemeId,
      'amount': amount,
      'purpose_id': ?purposeId,
      'emi_amount': ?emiAmount,
      'self_income': ?selfIncome,
      'husband_son_income': ?husbandSonIncome,
      'other_income': ?otherIncome,
      'resident_income': ?residentIncome,
      'house_expenses': ?houseExpenses,
      'mfi_expenses': ?mfiExpenses,
      'business_expenses': ?businessExpenses,
      if (sourceOfIncome != null && sourceOfIncome.isNotEmpty) 'source_of_income': sourceOfIncome,
      if (ifscCode != null && ifscCode.isNotEmpty) 'ifsc_code': ifscCode,
      if (bankName != null && bankName.isNotEmpty) 'bank_name': bankName,
      if (bankBranch != null && bankBranch.isNotEmpty) 'bank_branch': bankBranch,
      if (bankAddress != null && bankAddress.isNotEmpty) 'bank_address': bankAddress,
      if (accountNumber != null && accountNumber.isNotEmpty) 'account_number': accountNumber,
      if (beneficiaryName != null && beneficiaryName.isNotEmpty) 'beneficiary_name': beneficiaryName,
      if (memberFirstName != null && memberFirstName.isNotEmpty) 'member_first_name': memberFirstName,
      if (memberLastName != null && memberLastName.isNotEmpty) 'member_last_name': memberLastName,
      if (fatherHusbandName != null && fatherHusbandName.isNotEmpty) 'father_husband_name': fatherHusbandName,
      if (memberMobileNo != null && memberMobileNo.isNotEmpty) 'member_mobile_no': memberMobileNo,
      if (memberAddress != null && memberAddress.isNotEmpty) 'member_address': memberAddress,
      if (memberVillageCity != null && memberVillageCity.isNotEmpty) 'member_village_city': memberVillageCity,
      if (coBorrowerFirstName != null && coBorrowerFirstName.isNotEmpty) 'co_borrower_first_name': coBorrowerFirstName,
      if (coBorrowerLastName != null && coBorrowerLastName.isNotEmpty) 'co_borrower_last_name': coBorrowerLastName,
      if (coBorrowerAddress != null && coBorrowerAddress.isNotEmpty) 'co_borrower_address': coBorrowerAddress,
      if (coBorrowerVillageCity != null && coBorrowerVillageCity.isNotEmpty) 'co_borrower_village_city': coBorrowerVillageCity,
    });
  }

  /// Branch/officer-scoped worklist of members currently eligible for a reloan.
  Future<List<ReloanCandidate>> reloanEligible() async {
    final data = await _client.get(Endpoints.loansReloanEligible);
    return (data['items'] as List<dynamic>).map((e) => ReloanCandidate.fromJson(e as Map<String, dynamic>)).toList();
  }

  /// Branch-scoped list of loans at the Approved stage, awaiting disbursement.
  Future<List<PendingDisbursementItem>> pendingDisbursement() async {
    final data = await _client.get(Endpoints.loansPendingDisbursement);
    return (data['items'] as List<dynamic>).map((e) => PendingDisbursementItem.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<void> disburse({
    required int loanId,
    required double disbursedAmount,
    required String disbursementMode,
    String? disbursementRefNo,
    String? confirmAccountNumber,
  }) async {
    await _client.post(Endpoints.loansDisburse, data: {
      'loan_id': loanId,
      'disbursed_amount': disbursedAmount,
      'disbursement_mode': disbursementMode,
      if (disbursementRefNo != null && disbursementRefNo.isNotEmpty) 'disbursement_ref_no': disbursementRefNo,
      if (confirmAccountNumber != null && confirmAccountNumber.isNotEmpty) 'confirm_account_number': confirmAccountNumber,
    });
  }

  /// Captures the customer's photo at disbursement time -- required before
  /// [disburse] will let the loan move to Disbursed.
  Future<String> uploadDisbursementPhoto({required int loanId, required File photo}) async {
    final formData = FormData.fromMap({
      'loan_id': loanId,
      'photo': await MultipartFile.fromFile(photo.path, filename: photo.path.split('/').last),
    });
    final data = await _client.postMultipart(Endpoints.loansDisbursementPhotoUpload, formData);
    return data['photo_url'] as String;
  }

  /// Branch-scoped worklist of loans still needing a Tele-Verification call,
  /// plus the fixed set of statuses a TVR can be recorded as.
  Future<TvrPendingResult> tvrPending() async {
    final data = await _client.get(Endpoints.loansTvrPending);
    return TvrPendingResult(
      items: (data['items'] as List<dynamic>).map((e) => TvrPendingItem.fromJson(e as Map<String, dynamic>)).toList(),
      statuses: (data['tvr_statuses'] as List<dynamic>).map((e) => TvrStatusOption.fromJson(e as Map<String, dynamic>)).toList(),
    );
  }

  Future<void> createTvr({
    required int loanId,
    required String contactPersonName,
    required DateTime tvrDate,
    required int tvrStatusId,
    String? verificationNotes,
  }) async {
    final iso = '${tvrDate.year.toString().padLeft(4, '0')}-${tvrDate.month.toString().padLeft(2, '0')}-${tvrDate.day.toString().padLeft(2, '0')}';
    await _client.post(Endpoints.loansTvrCreate, data: {
      'loan_id': loanId,
      'contact_person_name': contactPersonName,
      'tvr_date': iso,
      'tvr_status_id': tvrStatusId,
      if (verificationNotes != null && verificationNotes.isNotEmpty) 'verification_notes': verificationNotes,
    });
  }
}

class TvrPendingResult {
  final List<TvrPendingItem> items;
  final List<TvrStatusOption> statuses;

  TvrPendingResult({required this.items, required this.statuses});
}

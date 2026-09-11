import 'dart:io';

import 'package:dio/dio.dart';

import '../models/approval.dart';
import '../models/loan.dart';
import '../models/reloan_candidate.dart';
import 'api_client.dart';
import 'endpoints.dart';

class LoanScheme {
  final int id;
  final String name;
  final int tenureMonths;
  final double defaultAmount;
  final double interestRatePercent;

  LoanScheme({
    required this.id,
    required this.name,
    required this.tenureMonths,
    required this.defaultAmount,
    required this.interestRatePercent,
  });

  factory LoanScheme.fromJson(Map<String, dynamic> json) => LoanScheme(
        id: json['id'] as int,
        name: json['name'] as String? ?? '',
        tenureMonths: json['tenure_months'] as int? ?? 0,
        defaultAmount: (json['default_amount'] as num?)?.toDouble() ?? 0,
        interestRatePercent: (json['interest_rate_percent'] as num?)?.toDouble() ?? 0,
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
    double? houseExpenses,
    double? mfiExpenses,
    double? businessExpenses,
    String? sourceOfIncome,
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
      'house_expenses': ?houseExpenses,
      'mfi_expenses': ?mfiExpenses,
      'business_expenses': ?businessExpenses,
      if (sourceOfIncome != null && sourceOfIncome.isNotEmpty) 'source_of_income': sourceOfIncome,
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
    String? disbursementRefNo,
  }) async {
    await _client.post(Endpoints.loansDisburse, data: {
      'loan_id': loanId,
      'disbursed_amount': disbursedAmount,
      if (disbursementRefNo != null && disbursementRefNo.isNotEmpty) 'disbursement_ref_no': disbursementRefNo,
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
}

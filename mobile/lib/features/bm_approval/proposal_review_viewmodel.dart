import 'package:flutter/foundation.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/loans_api.dart';
import '../../core/models/proposal_review.dart';

class ProposalReviewViewModel extends ChangeNotifier {
  final LoansApi _loansApi;
  final int loanId;

  ProposalReviewViewModel(this._loansApi, this.loanId) {
    _load();
  }

  bool loading = true;
  String? loadError;
  ProposalReviewDetail? detail;
  List<LoanScheme> schemes = [];
  List<LoanPurpose> purposes = [];

  bool saving = false;
  String? saveError;
  bool deciding = false;
  String? decideError;

  Future<void> _load() async {
    loading = true;
    loadError = null;
    notifyListeners();
    try {
      final results = await Future.wait([_loansApi.proposalReviewDetail(loanId), _loansApi.lookups()]);
      detail = results[0] as ProposalReviewDetail;
      final lookups = results[1] as LoanLookups;
      schemes = lookups.schemes;
      purposes = lookups.purposes;
    } on ApiException catch (e) {
      loadError = e.message;
    } finally {
      loading = false;
      notifyListeners();
    }
  }

  /// Saves the reviewer's corrections. Returns true on success.
  Future<bool> saveChanges({
    required int schemeId,
    required double amount,
    int? purposeId,
    double? emiAmount,
    double? selfIncome,
    double? husbandSonIncome,
    double? otherIncome,
    double? houseExpenses,
    double? mfiExpenses,
    double? businessExpenses,
    String? ifscCode,
    String? bankName,
    String? bankBranch,
    String? bankAddress,
    String? accountNumber,
    String? beneficiaryName,
  }) async {
    saving = true;
    saveError = null;
    notifyListeners();
    try {
      await _loansApi.updateProposal(
        loanId: loanId,
        schemeId: schemeId,
        amount: amount,
        purposeId: purposeId,
        emiAmount: emiAmount,
        selfIncome: selfIncome,
        husbandSonIncome: husbandSonIncome,
        otherIncome: otherIncome,
        houseExpenses: houseExpenses,
        mfiExpenses: mfiExpenses,
        businessExpenses: businessExpenses,
        ifscCode: ifscCode,
        bankName: bankName,
        bankBranch: bankBranch,
        bankAddress: bankAddress,
        accountNumber: accountNumber,
        beneficiaryName: beneficiaryName,
      );
      return true;
    } on ApiException catch (e) {
      saveError = e.message;
      return false;
    } finally {
      saving = false;
      notifyListeners();
    }
  }

  /// Approves or rejects. Returns true on success.
  Future<bool> decide({required int loanApprovalId, required bool approve, String? remarks}) async {
    deciding = true;
    decideError = null;
    notifyListeners();
    try {
      await _loansApi.reviewLoanApproval(loanApprovalId: loanApprovalId, approve: approve, remarks: remarks);
      return true;
    } on ApiException catch (e) {
      decideError = e.message;
      return false;
    } finally {
      deciding = false;
      notifyListeners();
    }
  }
}

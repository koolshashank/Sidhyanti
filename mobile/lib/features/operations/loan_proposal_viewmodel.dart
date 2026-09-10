import 'package:flutter/foundation.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/loans_api.dart';
import '../../core/api/members_api.dart';
import '../../core/models/member.dart';

class LoanProposalViewModel extends ChangeNotifier {
  final MembersApi _membersApi;
  final LoansApi _loansApi;

  LoanProposalViewModel(this._membersApi, this._loansApi, this.memberId) {
    _load();
  }

  final int memberId;

  bool loading = true;
  String? loadError;
  MemberDetail? member;
  List<LoanScheme> schemes = [];
  List<LoanPurpose> purposes = [];

  bool submitting = false;
  String? submitError;

  Future<void> _load() async {
    loading = true;
    loadError = null;
    notifyListeners();
    try {
      final results = await Future.wait([_membersApi.detail(memberId), _loansApi.lookups()]);
      member = results[0] as MemberDetail;
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

  Future<NewLoanResult?> submit({
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
  }) async {
    submitting = true;
    submitError = null;
    notifyListeners();
    try {
      return await _loansApi.createProposal(
        memberId: memberId,
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
      );
    } on ApiException catch (e) {
      submitError = e.message;
      return null;
    } finally {
      submitting = false;
      notifyListeners();
    }
  }
}

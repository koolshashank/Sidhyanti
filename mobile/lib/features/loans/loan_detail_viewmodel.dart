import 'package:flutter/foundation.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/loans_api.dart';
import '../../core/models/loan.dart';

class LoanDetailViewModel extends ChangeNotifier {
  final LoansApi _loansApi;
  final int loanId;

  LoanDetailViewModel(this._loansApi, this.loanId) {
    load();
  }

  LoanDetail? loan;
  bool loading = false;
  String? error;

  List<LoanScheme> schemeOptions = [];
  bool changingScheme = false;
  String? changeSchemeError;

  Future<void> load() async {
    loading = true;
    error = null;
    notifyListeners();
    try {
      loan = await _loansApi.detail(loanId);
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      loading = false;
      notifyListeners();
    }
  }

  Future<void> loadSchemeOptions() async {
    try {
      final lookups = await _loansApi.lookups();
      schemeOptions = lookups.schemes;
      notifyListeners();
    } on ApiException {
      // The picker will just show an empty list; the user can retry by reopening it.
    }
  }

  Future<bool> changeScheme(int newSchemeId) async {
    changingScheme = true;
    changeSchemeError = null;
    notifyListeners();
    try {
      await _loansApi.changeScheme(loanId: loanId, newSchemeId: newSchemeId);
      await load();
      return true;
    } on ApiException catch (e) {
      changeSchemeError = e.message;
      return false;
    } finally {
      changingScheme = false;
      notifyListeners();
    }
  }
}

import 'package:flutter/foundation.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/loans_api.dart';
import '../../core/models/tvr.dart';

class TvrViewModel extends ChangeNotifier {
  final LoansApi _loansApi;

  TvrViewModel(this._loansApi) {
    load();
  }

  bool loading = true;
  String? error;
  List<TvrPendingItem> items = [];
  List<TvrStatusOption> statuses = [];

  bool submitting = false;
  String? submitError;

  Future<void> load() async {
    loading = true;
    error = null;
    notifyListeners();
    try {
      final result = await _loansApi.tvrPending();
      items = result.items;
      statuses = result.statuses;
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      loading = false;
      notifyListeners();
    }
  }

  Future<bool> submit({
    required int loanId,
    required String contactPersonName,
    required DateTime tvrDate,
    required int tvrStatusId,
    String? verificationNotes,
  }) async {
    submitting = true;
    submitError = null;
    notifyListeners();
    try {
      await _loansApi.createTvr(
        loanId: loanId,
        contactPersonName: contactPersonName,
        tvrDate: tvrDate,
        tvrStatusId: tvrStatusId,
        verificationNotes: verificationNotes,
      );
      items = items.where((i) => i.loanId != loanId).toList();
      return true;
    } on ApiException catch (e) {
      submitError = e.message;
      return false;
    } finally {
      submitting = false;
      notifyListeners();
    }
  }
}

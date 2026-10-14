import 'dart:io';

import 'package:flutter/foundation.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/loans_api.dart';
import '../../core/models/grt_proposal_detail.dart';

class GrtViewModel extends ChangeNotifier {
  final LoansApi _loansApi;
  final int loanId;

  GrtViewModel(this._loansApi, this.loanId) {
    _loadDetail();
  }

  bool submitting = false;
  String? submitError;

  bool loadingDetail = true;
  String? loadError;
  GrtProposalDetail? detail;

  Future<void> _loadDetail() async {
    loadingDetail = true;
    loadError = null;
    notifyListeners();
    try {
      detail = await _loansApi.grtProposalDetail(loanId);
    } on ApiException catch (e) {
      loadError = e.message;
    } finally {
      loadingDetail = false;
      notifyListeners();
    }
  }

  Future<bool> submit({
    required bool passed,
    required DateTime grtDate,
    double? financeAmountVerified,
    String? remarks,
    File? documentFile,
  }) async {
    submitting = true;
    submitError = null;
    notifyListeners();
    try {
      await _loansApi.conductGrt(
        loanId: loanId,
        passed: passed,
        grtDate: grtDate,
        financeAmountVerified: financeAmountVerified,
        remarks: remarks,
        documentFile: documentFile,
      );
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

import 'package:flutter/foundation.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/hr_api.dart';

class SalaryAdvanceViewModel extends ChangeNotifier {
  final HrApi _hrApi;

  SalaryAdvanceViewModel(this._hrApi);

  bool submitting = false;
  String? error;

  Future<bool> submit({required String requestType, required double amount, String? reason}) async {
    submitting = true;
    error = null;
    notifyListeners();
    try {
      await _hrApi.createSalaryAdvanceRequest(requestType: requestType, amount: amount, reason: reason);
      return true;
    } on ApiException catch (e) {
      error = e.message;
      return false;
    } finally {
      submitting = false;
      notifyListeners();
    }
  }
}

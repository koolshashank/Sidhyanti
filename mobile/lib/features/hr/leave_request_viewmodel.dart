import 'package:flutter/foundation.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/hr_api.dart';

class LeaveRequestViewModel extends ChangeNotifier {
  final HrApi _hrApi;

  LeaveRequestViewModel(this._hrApi);

  bool submitting = false;
  String? error;

  Future<bool> submit({
    required String leaveType,
    required DateTime fromDate,
    required DateTime toDate,
    String? reason,
  }) async {
    submitting = true;
    error = null;
    notifyListeners();
    try {
      await _hrApi.createLeaveRequest(leaveType: leaveType, fromDate: fromDate, toDate: toDate, reason: reason);
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

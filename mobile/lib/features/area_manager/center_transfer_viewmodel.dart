import 'package:flutter/foundation.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/centers_api.dart';
import '../../core/models/center_transfer.dart';

class CenterTransferViewModel extends ChangeNotifier {
  final CentersApi _centersApi;

  CenterTransferViewModel(this._centersApi) {
    load();
  }

  bool loading = true;
  String? error;
  List<CenterForTransfer> centers = [];
  List<BranchStaffOption> staff = [];

  bool submitting = false;
  String? submitError;

  Future<void> load() async {
    loading = true;
    error = null;
    notifyListeners();
    try {
      final results = await Future.wait([_centersApi.branchCenters(), _centersApi.branchStaff()]);
      centers = results[0] as List<CenterForTransfer>;
      staff = results[1] as List<BranchStaffOption>;
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      loading = false;
      notifyListeners();
    }
  }

  /// Returns true on success. Errors are surfaced via [submitError].
  Future<bool> transfer({required int centerId, required int newStaffId, String? remark}) async {
    submitting = true;
    submitError = null;
    notifyListeners();
    try {
      await _centersApi.transferCenter(centerId: centerId, newStaffId: newStaffId, remark: remark);
      await load();
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

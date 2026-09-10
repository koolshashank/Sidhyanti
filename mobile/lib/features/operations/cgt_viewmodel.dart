import 'package:flutter/foundation.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/centers_api.dart';

class CgtViewModel extends ChangeNotifier {
  final CentersApi _centersApi;

  CgtViewModel(this._centersApi);

  bool submitting = false;
  String? submitError;

  Future<bool> submit({required int centerId, required DateTime cgtDate, String? remarks}) async {
    submitting = true;
    submitError = null;
    notifyListeners();
    try {
      await _centersApi.submitCgt(centerId: centerId, cgtDate: cgtDate, remarks: remarks);
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

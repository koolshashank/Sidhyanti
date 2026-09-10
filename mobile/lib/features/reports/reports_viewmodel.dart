import 'package:flutter/foundation.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/reports_api.dart';
import '../../core/models/hr.dart';

class ReportsViewModel extends ChangeNotifier {
  final ReportsApi _reportsApi;

  ReportsViewModel(this._reportsApi) {
    load();
  }

  bool loading = true;
  String? error;
  MyPerformance? performance;

  Future<void> load() async {
    loading = true;
    error = null;
    notifyListeners();
    try {
      performance = await _reportsApi.myPerformance();
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      loading = false;
      notifyListeners();
    }
  }
}

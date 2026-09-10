import 'package:flutter/foundation.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/centers_api.dart';
import '../../core/models/center_summary.dart';

class MyCentersViewModel extends ChangeNotifier {
  final CentersApi _centersApi;

  MyCentersViewModel(this._centersApi) {
    load();
  }

  bool loading = true;
  String? error;
  List<CenterSummary> centers = [];

  Future<void> load() async {
    loading = true;
    error = null;
    notifyListeners();
    try {
      centers = await _centersApi.myCenters();
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      loading = false;
      notifyListeners();
    }
  }
}

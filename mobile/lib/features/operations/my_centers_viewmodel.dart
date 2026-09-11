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
  String searchQuery = '';

  List<CenterSummary> get filteredCenters {
    final q = searchQuery.trim().toLowerCase();
    if (q.isEmpty) return centers;
    return centers.where((c) {
      return c.name.toLowerCase().contains(q) || (c.code?.toLowerCase().contains(q) ?? false);
    }).toList();
  }

  void setSearchQuery(String query) {
    searchQuery = query;
    notifyListeners();
  }

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

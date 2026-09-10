import 'package:flutter/foundation.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/collections_api.dart';
import '../../core/models/collection_due_item.dart';

class ArrearCentersViewModel extends ChangeNotifier {
  final CollectionsApi _collectionsApi;

  ArrearCentersViewModel(this._collectionsApi) {
    load();
  }

  List<ArrearCenterSummary> centers = [];
  bool loading = false;
  String? error;

  Future<void> load() async {
    loading = true;
    error = null;
    notifyListeners();
    try {
      centers = await _collectionsApi.arrearCentersSummary();
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      loading = false;
      notifyListeners();
    }
  }
}

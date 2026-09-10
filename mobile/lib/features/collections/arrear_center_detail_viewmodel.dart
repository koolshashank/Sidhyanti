import 'package:flutter/foundation.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/collections_api.dart';
import '../../core/models/collection_due_item.dart';

class ArrearCenterDetailViewModel extends ChangeNotifier {
  final CollectionsApi _collectionsApi;
  final int centerId;

  ArrearCenterDetailViewModel(this._collectionsApi, this.centerId) {
    load();
  }

  List<CollectionDueItem> items = [];
  bool loading = false;
  String? error;

  Future<void> load() async {
    loading = true;
    error = null;
    notifyListeners();
    try {
      items = await _collectionsApi.arrearDetail(centerId);
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      loading = false;
      notifyListeners();
    }
  }

  /// Arrears don't reduce cleanly like a single Collection-tab row (paying
  /// the next installment doesn't necessarily clear the whole overdue
  /// balance for that loan), so just reload from the server for correctness
  /// instead of trying to patch this row's state locally.
  Future<void> onCollected() => load();
}

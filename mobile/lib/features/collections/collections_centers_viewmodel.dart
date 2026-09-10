import 'package:flutter/foundation.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/collections_api.dart';
import '../../core/models/collection_due_item.dart';

enum DueRangePreset { today, yesterday, last7Days }

class CollectionsCentersViewModel extends ChangeNotifier {
  final CollectionsApi _collectionsApi;

  CollectionsCentersViewModel(this._collectionsApi) {
    load();
  }

  DueRangePreset preset = DueRangePreset.today;
  List<CollectionCenterSummary> centers = [];
  bool loading = false;
  String? error;

  DateTime get dateFrom {
    final today = DateTime.now();
    switch (preset) {
      case DueRangePreset.today:
        return DateTime(today.year, today.month, today.day);
      case DueRangePreset.yesterday:
        final y = today.subtract(const Duration(days: 1));
        return DateTime(y.year, y.month, y.day);
      case DueRangePreset.last7Days:
        final d = today.subtract(const Duration(days: 6));
        return DateTime(d.year, d.month, d.day);
    }
  }

  DateTime get dateTo {
    final today = DateTime.now();
    if (preset == DueRangePreset.yesterday) {
      final y = today.subtract(const Duration(days: 1));
      return DateTime(y.year, y.month, y.day);
    }
    return DateTime(today.year, today.month, today.day);
  }

  void setPreset(DueRangePreset value) {
    preset = value;
    load();
  }

  Future<void> load() async {
    loading = true;
    error = null;
    notifyListeners();
    try {
      centers = await _collectionsApi.centersSummary(dateFrom: dateFrom, dateTo: dateTo);
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      loading = false;
      notifyListeners();
    }
  }
}

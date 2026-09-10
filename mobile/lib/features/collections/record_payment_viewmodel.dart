import 'package:flutter/foundation.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/collections_api.dart';
import '../../core/models/collection_due_item.dart';

class RecordPaymentViewModel extends ChangeNotifier {
  final CollectionsApi _collectionsApi;
  final CollectionDueItem item;

  RecordPaymentViewModel(this._collectionsApi, this.item);

  bool submitting = false;
  String? error;

  Future<RecordPaymentResult?> submit({
    required double amount,
    required DateTime collectionDate,
    String? remarks,
  }) async {
    submitting = true;
    error = null;
    notifyListeners();
    try {
      final result = await _collectionsApi.record(
        loanId: item.loanId,
        installmentNo: item.installmentNo,
        amount: amount,
        collectionDate: collectionDate,
        remarks: remarks,
      );
      return result;
    } on ApiException catch (e) {
      error = e.isAlreadyRecorded ? 'This installment was already recorded.' : e.message;
      return null;
    } finally {
      submitting = false;
      notifyListeners();
    }
  }
}

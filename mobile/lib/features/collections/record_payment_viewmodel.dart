import 'package:flutter/foundation.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/collections_api.dart';
import '../../core/collections/collection_queue_store.dart';
import '../../core/models/collection_due_item.dart';
import '../../core/models/queued_collection.dart';

class RecordPaymentViewModel extends ChangeNotifier {
  final CollectionsApi _collectionsApi;
  final CollectionDueItem item;

  RecordPaymentViewModel(this._collectionsApi, this.item);

  bool submitting = false;
  String? error;

  /// True if the last successful [submit] call was queued locally (no
  /// network) rather than actually confirmed by the server.
  bool queuedOffline = false;

  Future<RecordPaymentResult?> submit({
    required double amount,
    required DateTime collectionDate,
    String? remarks,
  }) async {
    submitting = true;
    error = null;
    queuedOffline = false;
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
      if (e.isNetworkError) {
        await CollectionQueueStore.enqueue(QueuedCollection(
          loanId: item.loanId,
          scheduleId: item.scheduleId,
          installmentNo: item.installmentNo,
          amount: amount,
          collectionDate: collectionDate,
          remarks: remarks,
          borrowerName: item.borrowerName,
          queuedAt: DateTime.now(),
        ));
        queuedOffline = true;
        // No server confirmation yet -- scheduleId/status are the client's
        // own best-known values so the UI can still update optimistically.
        return RecordPaymentResult(
          loanId: item.loanId,
          scheduleId: item.scheduleId,
          installmentNo: item.installmentNo,
          collectedAmount: amount,
          status: 'QueuedOffline',
        );
      }
      error = e.isAlreadyRecorded ? 'This installment was already recorded.' : e.message;
      return null;
    } finally {
      submitting = false;
      notifyListeners();
    }
  }
}

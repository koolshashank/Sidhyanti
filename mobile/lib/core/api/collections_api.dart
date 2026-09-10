import 'package:intl/intl.dart';

import '../models/approval.dart';
import '../models/collection_due_item.dart';
import '../models/paginated_result.dart';
import 'api_client.dart';
import 'endpoints.dart';

final _isoDate = DateFormat('yyyy-MM-dd');

class CollectionsDuePage {
  final CollectionsSummary summary;
  final PaginatedResult<CollectionDueItem> page;

  CollectionsDuePage(this.summary, this.page);
}

class CollectionsApi {
  final ApiClient _client;

  CollectionsApi(this._client);

  Future<CollectionsDuePage> due({
    required DateTime dateFrom,
    required DateTime dateTo,
    int? centerId,
    int page = 1,
    int perPage = 50,
  }) async {
    final data = await _client.get(Endpoints.collectionsDue, query: {
      'date_from': _isoDate.format(dateFrom),
      'date_to': _isoDate.format(dateTo),
      // ignore: use_null_aware_elements
      if (centerId != null) 'center_id': centerId,
      'page': page,
      'per_page': perPage,
    });
    return CollectionsDuePage(
      CollectionsSummary.fromJson(data['summary'] as Map<String, dynamic>),
      PaginatedResult.fromJson(data, CollectionDueItem.fromJson),
    );
  }

  Future<List<CollectionCenterSummary>> centersSummary({DateTime? dateFrom, DateTime? dateTo}) async {
    final data = await _client.get(Endpoints.collectionsCentersSummary, query: {
      if (dateFrom != null) 'date_from': _isoDate.format(dateFrom),
      if (dateTo != null) 'date_to': _isoDate.format(dateTo),
    });
    return (data['items'] as List<dynamic>)
        .map((e) => CollectionCenterSummary.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<List<ArrearCenterSummary>> arrearCentersSummary() async {
    final data = await _client.get(Endpoints.collectionsArrearCentersSummary);
    return (data['items'] as List<dynamic>)
        .map((e) => ArrearCenterSummary.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<List<CollectionDueItem>> arrearDetail(int centerId) async {
    final data = await _client.get(Endpoints.collectionsArrearDetail, query: {'center_id': centerId});
    return (data['items'] as List<dynamic>).map((e) => CollectionDueItem.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<AttendanceResult> getAttendance({required int centerId, required DateTime date}) async {
    final data = await _client.get(Endpoints.collectionsAttendance, query: {
      'center_id': centerId,
      'date': _isoDate.format(date),
    });
    return AttendanceResult.fromJson(data);
  }

  Future<AttendanceResult> markAttendance({
    required int centerId,
    required DateTime date,
    required List<Map<String, dynamic>> records,
  }) async {
    final data = await _client.post(Endpoints.collectionsAttendanceMark, data: {
      'center_id': centerId,
      'date': _isoDate.format(date),
      'records': records,
    });
    return AttendanceResult.fromJson(data);
  }

  Future<RecordPaymentResult> record({
    required int loanId,
    required int installmentNo,
    required double amount,
    DateTime? collectionDate,
    String? remarks,
  }) async {
    final data = await _client.post(Endpoints.collectionsRecord, data: {
      'loan_id': loanId,
      'installment_no': installmentNo,
      'amount': amount,
      if (collectionDate != null) 'collection_date': _isoDate.format(collectionDate),
      if (remarks != null && remarks.isNotEmpty) 'remarks': remarks,
    });
    return RecordPaymentResult.fromJson(data);
  }

  /// Reviewer-facing queue of partial payments awaiting Branch Manager approval.
  Future<List<PartialPaymentApprovalItem>> pendingPartialPayments() async {
    final data = await _client.get(Endpoints.collectionsPendingPartialPayments);
    return (data['items'] as List<dynamic>).map((e) => PartialPaymentApprovalItem.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<void> reviewPartialPayment({
    required int partialPaymentApprovalId,
    required bool approve,
    String? remarks,
  }) async {
    await _client.post(Endpoints.collectionsPartialPaymentApprove, data: {
      'partial_payment_approval_id': partialPaymentApprovalId,
      'action': approve ? 'approve' : 'reject',
      if (remarks != null && remarks.isNotEmpty) 'remarks': remarks,
    });
  }
}

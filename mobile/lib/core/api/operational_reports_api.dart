import '../models/operational_report.dart';
import 'api_client.dart';
import 'endpoints.dart';

/// Row-scoped operational reports (Field Officer -> own book, Branch
/// Manager -> own branch, Area Manager -> every mapped branch, Admin ->
/// everything) -- scoping happens entirely server-side via the same
/// loan_scope_where()/collection_scope_where() helpers the rest of the API
/// already uses, so this class just fetches and parses.
class OperationalReportsApi {
  final ApiClient _client;

  OperationalReportsApi(this._client);

  Future<List<CenterCollectionRow>> centerCollection({DateTime? from, DateTime? to}) async {
    final data = await _client.get(Endpoints.reportsCenterCollection, query: {
      if (from != null) 'from': _isoDate(from),
      if (to != null) 'to': _isoDate(to),
    });
    return (data['items'] as List<dynamic>).map((e) => CenterCollectionRow.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<List<TodayPendingRow>> todayPendingCollection() async {
    final data = await _client.get(Endpoints.reportsTodayPendingCollection);
    return (data['items'] as List<dynamic>).map((e) => TodayPendingRow.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<List<TodayDisbursedRow>> todayDisbursed() async {
    final data = await _client.get(Endpoints.reportsTodayDisbursed);
    return (data['items'] as List<dynamic>).map((e) => TodayDisbursedRow.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<List<DueDisbursementRow>> dueDisbursement() async {
    final data = await _client.get(Endpoints.reportsDueDisbursement);
    return (data['items'] as List<dynamic>).map((e) => DueDisbursementRow.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<List<ArrearsClientRow>> arrearsClients() async {
    final data = await _client.get(Endpoints.reportsArrearsClients);
    return (data['items'] as List<dynamic>).map((e) => ArrearsClientRow.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<List<DefaultLoanRow>> defaultLoans() async {
    final data = await _client.get(Endpoints.reportsDefaultLoans);
    return (data['items'] as List<dynamic>).map((e) => DefaultLoanRow.fromJson(e as Map<String, dynamic>)).toList();
  }

  String _isoDate(DateTime d) =>
      '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';
}

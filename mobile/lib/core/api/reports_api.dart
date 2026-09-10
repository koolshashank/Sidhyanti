import 'package:intl/intl.dart';

import '../models/approval.dart';
import '../models/hr.dart';
import 'api_client.dart';
import 'endpoints.dart';

final _isoDate = DateFormat('yyyy-MM-dd');

class ReportsApi {
  final ApiClient _client;

  ReportsApi(this._client);

  Future<MyPerformance> myPerformance() async {
    final data = await _client.get(Endpoints.reportsMyPerformance);
    return MyPerformance.fromJson(data);
  }

  /// Daily Collection Report for [date] (defaults to today), optionally
  /// drilled down to a single [centerId].
  Future<DcrReport> dcr({required DateTime date, int? centerId}) async {
    final data = await _client.get(Endpoints.reportsDcr, query: {
      'date': _isoDate.format(date),
      // ignore: use_null_aware_elements
      if (centerId != null) 'center_id': centerId,
    });
    return DcrReport.fromJson(data);
  }
}

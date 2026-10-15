import '../models/duty_session.dart';
import 'api_client.dart';
import 'endpoints.dart';

class TrackingApi {
  final ApiClient _client;

  TrackingApi(this._client);

  Future<void> submitConsent(bool consent) async {
    await _client.post(Endpoints.trackingConsent, data: {'consent': consent});
  }

  Future<DutySessionInfo> startDuty() async {
    final data = await _client.post(Endpoints.trackingDutyStart);
    return DutySessionInfo.fromJson(data);
  }

  Future<void> endDuty(int sessionId) async {
    await _client.post(Endpoints.trackingDutyEnd, data: {'session_id': sessionId});
  }

  /// Uploads a batch of queued points for [sessionId]. Returns how many were
  /// actually inserted (malformed points are silently skipped server-side).
  Future<int> sendPing({required int sessionId, required List<LocationPoint> points}) async {
    final data = await _client.post(Endpoints.trackingPing, data: {
      'session_id': sessionId,
      'points': points.map((p) => p.toJson()).toList(),
    });
    return data['inserted'] as int? ?? 0;
  }

  /// Admin only: every staff member currently on an active duty session.
  Future<List<StaffLiveLocation>> liveMap() async {
    final data = await _client.get(Endpoints.trackingLiveMap);
    return (data['staff'] as List<dynamic>)
        .map((e) => StaffLiveLocation.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  /// Admin only: a staff member's full filtered route for one calendar day.
  Future<StaffRouteResult> route({required int staffId, required DateTime date}) async {
    final data = await _client.get(Endpoints.trackingRoute, query: {
      'staff_id': staffId,
      'date': _isoDate(date),
    });
    return StaffRouteResult.fromJson(data);
  }

  String _isoDate(DateTime d) =>
      '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';
}

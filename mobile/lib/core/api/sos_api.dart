import 'api_client.dart';
import 'endpoints.dart';

class SosTriggerResult {
  final int sosAlertId;
  final int notifiedCount;

  SosTriggerResult({required this.sosAlertId, required this.notifiedCount});

  factory SosTriggerResult.fromJson(Map<String, dynamic> json) => SosTriggerResult(
        sosAlertId: json['sos_alert_id'] as int,
        notifiedCount: json['notified_count'] as int? ?? 0,
      );
}

class SosApi {
  final ApiClient _client;

  SosApi(this._client);

  Future<SosTriggerResult> trigger({double? latitude, double? longitude}) async {
    final data = await _client.post(Endpoints.sosTrigger, data: {
      'latitude': ?latitude,
      'longitude': ?longitude,
    });
    return SosTriggerResult.fromJson(data);
  }
}

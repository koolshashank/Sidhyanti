import 'api_client.dart';
import 'endpoints.dart';

class AppVersionInfo {
  final int versionCode;
  final String versionName;
  final String apkUrl;
  final String releaseNotes;
  final bool forceUpdate;

  AppVersionInfo({
    required this.versionCode,
    required this.versionName,
    required this.apkUrl,
    required this.releaseNotes,
    required this.forceUpdate,
  });

  factory AppVersionInfo.fromJson(Map<String, dynamic> json) => AppVersionInfo(
        versionCode: json['version_code'] as int? ?? 0,
        versionName: json['version_name'] as String? ?? '',
        apkUrl: json['apk_url'] as String? ?? '',
        releaseNotes: json['release_notes'] as String? ?? '',
        forceUpdate: json['force_update'] as bool? ?? false,
      );
}

/// No login required -- checked from the splash screen before the user is
/// necessarily authenticated.
class AppVersionApi {
  final ApiClient _client;

  AppVersionApi(this._client);

  Future<AppVersionInfo> latest() async {
    final data = await _client.get(Endpoints.appVersion);
    return AppVersionInfo.fromJson(data);
  }
}

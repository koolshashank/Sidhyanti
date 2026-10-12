import 'dart:io';

import 'package:android_id/android_id.dart';

import '../api/api_client.dart';
import '../api/api_exception.dart';
import '../api/endpoints.dart';
import '../models/staff.dart';
import 'token_storage.dart';

class AuthRepository {
  final ApiClient _apiClient;
  final TokenStorage _tokenStorage;

  AuthRepository(this._apiClient, this._tokenStorage);

  Future<Staff> login(String username, String password) async {
    // Used server-side for device-binding on roles that have it enabled
    // (Field Officer / Branch Manager by default) -- null/empty on
    // non-Android platforms, which the server treats as "can't verify,
    // don't lock".
    final deviceId = await const AndroidId().getId().catchError((_) => null);
    final data = await _apiClient.post(Endpoints.login, data: {
      'username': username,
      'password': password,
      'device_info': Platform.operatingSystem,
      if (deviceId != null && deviceId.isNotEmpty) 'device_id': deviceId,
    });
    final staff = Staff.fromJson(data['staff'] as Map<String, dynamic>);
    await _tokenStorage.save(
      accessToken: data['access_token'] as String,
      refreshToken: data['refresh_token'] as String,
      staff: staff,
    );
    _apiClient.setAccessToken(data['access_token'] as String);
    return staff;
  }

  /// Restores a previously logged-in session from secure storage, if any.
  Future<Staff?> restoreSession() async {
    final accessToken = await _tokenStorage.readAccessToken();
    final staff = await _tokenStorage.readStaff();
    if (accessToken == null || staff == null) return null;
    _apiClient.setAccessToken(accessToken);
    return staff;
  }

  /// Re-fetches the current staff's profile (role/designation/menu_items)
  /// from the server without a full login -- so an Admin's change to
  /// Designation Mobile Menu Access can show up from a simple
  /// pull-to-refresh instead of forcing a logout/login.
  Future<Staff> refreshProfile() async {
    final data = await _apiClient.get(Endpoints.authMe);
    final staff = Staff.fromJson(data['staff'] as Map<String, dynamic>);
    await _tokenStorage.updateStaff(staff);
    return staff;
  }

  Future<void> logout() async {
    final refreshToken = await _tokenStorage.readRefreshToken();
    try {
      await _apiClient.post(Endpoints.logout, data: {'refresh_token': refreshToken});
    } on ApiException {
      // Best-effort: a failed network call must not block local sign-out.
    }
    _apiClient.setAccessToken(null);
    await _tokenStorage.clear();
  }

  Future<void> clearLocalSession() async {
    _apiClient.setAccessToken(null);
    await _tokenStorage.clear();
  }
}

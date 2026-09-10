import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../models/staff.dart';

/// Persists auth state (access/refresh tokens + the logged-in staff blob)
/// across app restarts. Backed by the platform keychain/credential store.
class TokenStorage {
  static const _accessTokenKey = 'access_token';
  static const _refreshTokenKey = 'refresh_token';
  static const _staffKey = 'staff';

  final _storage = const FlutterSecureStorage();

  Future<void> save({required String accessToken, required String refreshToken, required Staff staff}) async {
    await Future.wait([
      _storage.write(key: _accessTokenKey, value: accessToken),
      _storage.write(key: _refreshTokenKey, value: refreshToken),
      _storage.write(key: _staffKey, value: jsonEncode(staff.toJson())),
    ]);
  }

  Future<void> updateAccessToken(String accessToken) => _storage.write(key: _accessTokenKey, value: accessToken);

  Future<void> updateStaff(Staff staff) => _storage.write(key: _staffKey, value: jsonEncode(staff.toJson()));

  Future<String?> readAccessToken() => _storage.read(key: _accessTokenKey);

  Future<String?> readRefreshToken() => _storage.read(key: _refreshTokenKey);

  Future<Staff?> readStaff() async {
    final raw = await _storage.read(key: _staffKey);
    if (raw == null) return null;
    return Staff.fromJson(jsonDecode(raw) as Map<String, dynamic>);
  }

  Future<void> clear() async {
    await Future.wait([
      _storage.delete(key: _accessTokenKey),
      _storage.delete(key: _refreshTokenKey),
      _storage.delete(key: _staffKey),
    ]);
  }
}

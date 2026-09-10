import 'package:flutter/foundation.dart';

import '../api/api_client.dart';
import '../models/staff.dart';
import 'auth_repository.dart';

enum AuthStatus { unknown, authenticated, unauthenticated }

/// App-wide auth state, exposed via provider so `go_router`'s redirect and
/// every screen can react to sign-in/sign-out/session-expiry.
class AuthState extends ChangeNotifier {
  final AuthRepository _authRepository;
  final ApiClient _apiClient;

  AuthStatus status = AuthStatus.unknown;
  Staff? currentStaff;
  String? sessionMessage;

  AuthState(this._authRepository, this._apiClient) {
    _apiClient.onSessionExpired = _handleSessionExpired;
  }

  Future<void> restoreSession() async {
    final staff = await _authRepository.restoreSession();
    currentStaff = staff;
    status = staff != null ? AuthStatus.authenticated : AuthStatus.unauthenticated;
    notifyListeners();
  }

  Future<void> login(String username, String password) async {
    final staff = await _authRepository.login(username, password);
    currentStaff = staff;
    sessionMessage = null;
    status = AuthStatus.authenticated;
    notifyListeners();
  }

  /// Re-fetches the current staff's profile (role/designation/menu_items)
  /// so an Admin's change to Designation Mobile Menu Access reflects
  /// immediately from a pull-to-refresh, no re-login needed. Best-effort:
  /// a network hiccup here just leaves the previously cached profile as-is.
  Future<void> refreshProfile() async {
    try {
      currentStaff = await _authRepository.refreshProfile();
      notifyListeners();
    } catch (_) {
      // Keep showing the last known profile.
    }
  }

  Future<void> logout() async {
    await _authRepository.logout();
    currentStaff = null;
    status = AuthStatus.unauthenticated;
    notifyListeners();
  }

  void _handleSessionExpired() {
    _authRepository.clearLocalSession();
    currentStaff = null;
    sessionMessage = 'Session expired, please log in again.';
    status = AuthStatus.unauthenticated;
    notifyListeners();
  }
}

import 'dart:async';

import 'package:flutter/foundation.dart';

import '../api/api_client.dart';
import '../api/hr_api.dart';
import '../models/staff.dart';
import 'auth_repository.dart';
import 'pin_service.dart';

enum AuthStatus { unknown, authenticated, unauthenticated }

/// App-wide auth state, exposed via provider so `go_router`'s redirect and
/// every screen can react to sign-in/sign-out/session-expiry.
class AuthState extends ChangeNotifier {
  final AuthRepository _authRepository;
  final ApiClient _apiClient;
  final HrApi _hrApi;

  AuthStatus status = AuthStatus.unknown;
  Staff? currentStaff;
  String? sessionMessage;

  /// True once a logged-in staff member is known not to have checked in yet
  /// today -- the router redirects everywhere to the attendance gate screen
  /// until [markAttendanceDone] clears it.
  bool attendanceRequired = false;

  /// True whenever an app-lock PIN is set (see PinService/Account screen)
  /// and the app was just opened or resumed from the background -- the
  /// router redirects everywhere to the PIN lock screen until
  /// [markPinUnlocked] clears it. Checked before attendance, since the
  /// device itself should be unlocked before anything else happens.
  bool pinRequired = false;

  AuthState(this._authRepository, this._apiClient, this._hrApi) {
    _apiClient.onSessionExpired = _handleSessionExpired;
    _apiClient.onAttendanceRequired = _handleAttendanceRequired;
  }

  Future<void> restoreSession() async {
    final staff = await _authRepository.restoreSession();
    currentStaff = staff;
    status = staff != null ? AuthStatus.authenticated : AuthStatus.unauthenticated;
    notifyListeners();
    if (staff != null) {
      await _refreshPinLockStatus();
      await _refreshAttendanceStatus();
    }
  }

  Future<void> login(String username, String password) async {
    final staff = await _authRepository.login(username, password);
    currentStaff = staff;
    sessionMessage = null;
    status = AuthStatus.authenticated;
    notifyListeners();
    // A fresh login already proved identity -- no need to immediately
    // re-demand the PIN too.
    await _refreshAttendanceStatus();
  }

  Future<void> _refreshPinLockStatus() async {
    pinRequired = await PinService.hasPin();
    notifyListeners();
  }

  /// Re-checked on every app resume (see app.dart's didChangeAppLifecycleState)
  /// so coming back from the background always re-demands the PIN if one is set.
  Future<void> recheckPinOnResume() async {
    if (status == AuthStatus.authenticated) await _refreshPinLockStatus();
  }

  /// Called by the PIN lock screen right after a correct PIN is entered.
  void markPinUnlocked() {
    pinRequired = false;
    notifyListeners();
  }

  /// Checked right after login/restoreSession, and again whenever the app
  /// resumes from the background -- catches the day having rolled over to a
  /// fresh "not checked in yet" while the app sat open overnight.
  Future<void> _refreshAttendanceStatus() async {
    try {
      final today = await _hrApi.attendanceToday();
      attendanceRequired = !today.checkedIn;
      notifyListeners();
    } catch (_) {
      // Network hiccup -- don't lock the user out on a failed check; the
      // next protected API call (if any) will enforce it server-side anyway.
    }
  }

  /// Re-checks on app resume so a day-rollover while the app was backgrounded
  /// overnight is caught without needing to hit some other API first.
  Future<void> recheckAttendanceOnResume() async {
    if (status == AuthStatus.authenticated) await _refreshAttendanceStatus();
  }

  /// Called by the attendance gate screen right after a successful check-in.
  void markAttendanceDone() {
    attendanceRequired = false;
    notifyListeners();
  }

  void _handleAttendanceRequired() {
    if (status == AuthStatus.authenticated && !attendanceRequired) {
      attendanceRequired = true;
      notifyListeners();
    }
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
    await PinService.clearPin();
    currentStaff = null;
    attendanceRequired = false;
    pinRequired = false;
    status = AuthStatus.unauthenticated;
    notifyListeners();
  }

  void _handleSessionExpired() {
    _authRepository.clearLocalSession();
    unawaited(PinService.clearPin());
    currentStaff = null;
    attendanceRequired = false;
    pinRequired = false;
    sessionMessage = 'Session expired, please log in again.';
    status = AuthStatus.unauthenticated;
    notifyListeners();
  }
}

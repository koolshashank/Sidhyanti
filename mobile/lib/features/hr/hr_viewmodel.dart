import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:geolocator/geolocator.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/hr_api.dart';
import '../../core/models/hr.dart';

class HrViewModel extends ChangeNotifier {
  final HrApi _hrApi;

  HrViewModel(this._hrApi) {
    _load();
  }

  bool loading = true;
  String? error;
  AttendanceStatus? attendance;
  List<AttendanceHistoryItem> history = [];
  List<LeaveRequestItem> leaveRequests = [];

  bool checkingInOut = false;
  String? attendanceActionError;

  Future<void> _load() async {
    loading = true;
    error = null;
    notifyListeners();
    try {
      final results = await Future.wait([
        _hrApi.attendanceToday(),
        _hrApi.attendanceHistory(),
        _hrApi.leaveRequests(),
      ]);
      attendance = results[0] as AttendanceStatus;
      history = results[1] as List<AttendanceHistoryItem>;
      leaveRequests = results[2] as List<LeaveRequestItem>;
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      loading = false;
      notifyListeners();
    }
  }

  Future<void> refresh() => _load();

  Future<(double?, double?)> _tryGetLocation() async {
    try {
      final position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(accuracy: LocationAccuracy.high, timeLimit: Duration(seconds: 12)),
      );
      return (position.latitude, position.longitude);
    } on TimeoutException {
      final last = await Geolocator.getLastKnownPosition();
      return (last?.latitude, last?.longitude);
    } catch (_) {
      return (null, null);
    }
  }

  Future<bool> checkIn() async {
    checkingInOut = true;
    attendanceActionError = null;
    notifyListeners();
    try {
      final (lat, lng) = await _tryGetLocation();
      await _hrApi.checkIn(latitude: lat, longitude: lng);
      await _load();
      return true;
    } on ApiException catch (e) {
      attendanceActionError = e.message;
      return false;
    } finally {
      checkingInOut = false;
      notifyListeners();
    }
  }

  Future<bool> checkOut() async {
    checkingInOut = true;
    attendanceActionError = null;
    notifyListeners();
    try {
      final (lat, lng) = await _tryGetLocation();
      await _hrApi.checkOut(latitude: lat, longitude: lng);
      await _load();
      return true;
    } on ApiException catch (e) {
      attendanceActionError = e.message;
      return false;
    } finally {
      checkingInOut = false;
      notifyListeners();
    }
  }
}

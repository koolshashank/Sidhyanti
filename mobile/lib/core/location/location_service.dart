import 'dart:async';

import 'package:geolocator/geolocator.dart';

enum LocationStatus { granted, serviceDisabled, permissionDenied, permissionDeniedForever }

/// Checks whether the device's location service is on and this app has
/// permission to read it -- gates the dashboard per product requirement,
/// it does not itself read/store any coordinates.
class LocationService {
  Future<LocationStatus> checkStatus() async {
    final serviceEnabled = await Geolocator.isLocationServiceEnabled();
    if (!serviceEnabled) return LocationStatus.serviceDisabled;

    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }
    if (permission == LocationPermission.denied) return LocationStatus.permissionDenied;
    if (permission == LocationPermission.deniedForever) return LocationStatus.permissionDeniedForever;
    return LocationStatus.granted;
  }

  Future<void> openLocationSettings() => Geolocator.openLocationSettings();

  Future<void> openAppSettings() => Geolocator.openAppSettings();

  /// Best-effort current position -- (null, null) on any failure (denied
  /// permission, GPS timeout, disabled service). Callers that must not be
  /// blocked by location (e.g. an SOS trigger) should treat that as "send
  /// without coordinates," never as a hard failure.
  Future<(double?, double?)> tryGetCurrentPosition() async {
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
}

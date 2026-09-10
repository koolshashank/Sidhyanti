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
}

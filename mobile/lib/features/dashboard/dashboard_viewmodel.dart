import 'package:flutter/foundation.dart';

import '../../core/location/location_service.dart';

class DashboardViewModel extends ChangeNotifier {
  final LocationService _locationService;

  DashboardViewModel(this._locationService) {
    checkLocation();
  }

  LocationStatus? locationStatus;
  bool locationChecking = false;

  Future<void> checkLocation() async {
    locationChecking = true;
    notifyListeners();
    locationStatus = await _locationService.checkStatus();
    locationChecking = false;
    notifyListeners();
  }

  Future<void> openLocationSettings() => _locationService.openLocationSettings();

  Future<void> openAppSettings() => _locationService.openAppSettings();
}

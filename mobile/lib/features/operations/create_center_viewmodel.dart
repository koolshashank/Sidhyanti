import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:geocoding/geocoding.dart';
import 'package:geolocator/geolocator.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/centers_api.dart';
import '../../core/models/lookup_option.dart';

class CreateCenterViewModel extends ChangeNotifier {
  final CentersApi _centersApi;
  final Geocoding _geocoding = Geocoding();

  CreateCenterViewModel(this._centersApi) {
    _loadStates();
    _captureLocation();
  }

  bool statesLoading = true;
  List<LookupOption> states = [];

  bool locating = true;
  double? latitude;
  double? longitude;
  String? locationError;

  // Kept separate from `locating`: the GPS fix can complete quickly while
  // reverse-geocoding (a separate network/OS call) takes longer or times
  // out -- the coordinates shouldn't stay hidden while that resolves.
  bool geocodingInProgress = false;

  // Reverse-geocoded from the captured coordinates -- prefilled into the
  // form's Address/State/Pincode fields, but left editable since geocoding
  // results can be imprecise (e.g. a rural center with no exact address).
  String? geocodedAddress;
  int? geocodedStateId;
  String? geocodedStateName;
  String? geocodedPincode;
  String? geocodeError;

  bool submitting = false;
  String? submitError;

  Future<void> _loadStates() async {
    statesLoading = true;
    notifyListeners();
    try {
      states = await _centersApi.lookupStates();
      _matchGeocodedState();
    } on ApiException {
      // States dropdown will just be empty -- the user can still save
      // without a state rather than being blocked by this.
    } finally {
      statesLoading = false;
      notifyListeners();
    }
  }

  Future<void> _captureLocation() async {
    locating = true;
    locationError = null;
    notifyListeners();
    try {
      final position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(accuracy: LocationAccuracy.high, timeLimit: Duration(seconds: 12)),
      );
      latitude = position.latitude;
      longitude = position.longitude;
    } on TimeoutException {
      // No fresh GPS fix within the time limit (weak signal / indoors) --
      // fall back to the last known fix rather than leaving the user stuck.
      final last = await Geolocator.getLastKnownPosition();
      if (last != null) {
        latitude = last.latitude;
        longitude = last.longitude;
      } else {
        locationError = "Couldn't get a GPS fix in time. Move to an open area and retry, or save without it.";
      }
    } catch (_) {
      locationError = "Couldn't capture the current location. You can still save without it, or retry.";
    } finally {
      locating = false;
      notifyListeners();
    }
    // Fire-and-forget: the coordinates above are already usable on their own
    // (and shown immediately), address/state/pincode just fill in afterwards.
    if (latitude != null && longitude != null) {
      unawaited(_reverseGeocode(latitude!, longitude!));
    }
  }

  Future<void> _reverseGeocode(double lat, double lng) async {
    geocodingInProgress = true;
    geocodeError = null;
    notifyListeners();
    try {
      final placemarks = await _geocoding.placemarkFromCoordinates(lat, lng).timeout(const Duration(seconds: 10));
      if (placemarks.isEmpty) {
        geocodeError = "Couldn't find an address for this location -- enter it manually.";
        return;
      }
      final p = placemarks.first;
      geocodedAddress = [p.street, p.subLocality, p.locality].where((s) => s != null && s.isNotEmpty).join(', ');
      geocodedPincode = p.postalCode;
      _matchGeocodedState(name: p.administrativeArea);
    } catch (_) {
      // Reverse geocoding depends on the device's own Geocoder backend
      // (Play Services), which isn't guaranteed to be present/responsive on
      // every device -- fail quietly into manual entry rather than blocking.
      geocodeError = "Address lookup isn't available on this device -- enter it manually.";
    } finally {
      geocodingInProgress = false;
      notifyListeners();
    }
  }

  /// Matches the reverse-geocoded state name (e.g. "Haryana") against
  /// State_Master's options so the dropdown can be pre-selected -- Center.
  /// StateID is a foreign key, so a free-text guess can't be saved directly.
  ///
  /// Reverse-geocoding and the states API call race each other, and either
  /// can finish first -- the name is always remembered here (even if states
  /// haven't loaded yet) so the match retried once the other one lands is
  /// never comparing against a null name.
  void _matchGeocodedState({String? name}) {
    if (name != null) {
      geocodedStateName = name;
    }
    final target = geocodedStateName;
    if (target == null || states.isEmpty) return;
    for (final s in states) {
      if (s.name.toLowerCase() == target.toLowerCase()) {
        geocodedStateId = s.id;
        return;
      }
    }
  }

  void retryLocation() => _captureLocation();

  Future<NewCenterResult?> submit({
    required String centerName,
    String? centerPlace,
    String? address,
    int? stateId,
    String? pincode,
    String? meetingDay,
    String? meetingTime,
    String? contactPerson,
    String? contactNo,
  }) async {
    submitting = true;
    submitError = null;
    notifyListeners();
    try {
      return await _centersApi.create(
        centerName: centerName,
        centerPlace: centerPlace,
        address: address,
        stateId: stateId,
        pincode: pincode,
        meetingDay: meetingDay,
        meetingTime: meetingTime,
        contactPerson: contactPerson,
        contactNo: contactNo,
        latitude: latitude,
        longitude: longitude,
      );
    } on ApiException catch (e) {
      submitError = e.message;
      return null;
    } finally {
      submitting = false;
      notifyListeners();
    }
  }
}

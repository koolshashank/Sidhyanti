import 'dart:async';

import 'package:flutter/foundation.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/collections_api.dart';
import '../../core/api/members_api.dart';
import '../../core/location/location_service.dart';
import '../../core/models/collection_due_item.dart';

class DashboardViewModel extends ChangeNotifier {
  final LocationService _locationService;
  final MembersApi _membersApi;
  final CollectionsApi _collectionsApi;

  DashboardViewModel(this._locationService, this._membersApi, this._collectionsApi) {
    checkLocation();
  }

  LocationStatus? locationStatus;
  bool locationChecking = false;

  bool statsLoading = false;
  String? statsError;
  int? memberCount;
  CollectionsSummary? todaysCollections;

  Future<void> checkLocation() async {
    locationChecking = true;
    notifyListeners();
    locationStatus = await _locationService.checkStatus();
    locationChecking = false;
    notifyListeners();
    if (locationStatus == LocationStatus.granted) {
      unawaited(_loadStats());
    }
  }

  Future<void> openLocationSettings() => _locationService.openLocationSettings();

  Future<void> openAppSettings() => _locationService.openAppSettings();

  Future<void> _loadStats() async {
    statsLoading = true;
    statsError = null;
    notifyListeners();
    try {
      final today = DateTime.now();
      final membersFuture = _membersApi.list(page: 1, perPage: 1);
      final collectionsFuture = _collectionsApi.due(dateFrom: today, dateTo: today);
      final membersResult = await membersFuture;
      final collectionsResult = await collectionsFuture;
      memberCount = membersResult.total;
      todaysCollections = collectionsResult.summary;
    } on ApiException catch (e) {
      statsError = e.message;
    } finally {
      statsLoading = false;
      notifyListeners();
    }
  }
}

import 'package:flutter/foundation.dart';

import '../../core/api/centers_api.dart';
import '../../core/api/collections_api.dart';
import '../../core/api/hr_api.dart';
import '../../core/api/loans_api.dart';
import '../../core/location/location_service.dart';

class DashboardViewModel extends ChangeNotifier {
  final LocationService _locationService;
  final LoansApi _loansApi;
  final CentersApi _centersApi;
  final HrApi _hrApi;
  final CollectionsApi _collectionsApi;

  DashboardViewModel(this._locationService, this._loansApi, this._centersApi, this._hrApi, this._collectionsApi) {
    checkLocation();
  }

  LocationStatus? locationStatus;
  bool locationChecking = false;

  /// Menu key -> how many items are waiting on this staff member's action
  /// right now (pending loan proposals, centers, leave requests, etc.) --
  /// shown as a small badge on that menu's DashboardCard so a BM/AM doesn't
  /// have to open every queue just to see if anything is there.
  Map<String, int> pendingCounts = {};

  Future<void> checkLocation() async {
    locationChecking = true;
    notifyListeners();
    locationStatus = await _locationService.checkStatus();
    locationChecking = false;
    notifyListeners();
  }

  Future<void> openLocationSettings() => _locationService.openLocationSettings();

  Future<void> openAppSettings() => _locationService.openAppSettings();

  /// Best-effort: each menu's count is fetched independently, and a staff
  /// member missing the underlying permission for one of a menu's queues
  /// (e.g. "BM Approval" granted but not "approve_partial_payment") just
  /// contributes 0 from that queue rather than failing the whole badge.
  Future<void> loadPendingCounts(List<String> menuItems) async {
    final updates = <String, int>{};
    await Future.wait([
      if (menuItems.contains('bm_approval'))
        _sumCounts([
          () => _loansApi.pendingApprovals('Proposal'),
          () => _loansApi.pendingApprovals('Preclose'),
          () => _hrApi.pendingLeaveRequests(),
          () => _collectionsApi.pendingPartialPayments(),
        ]).then((n) => updates['bm_approval'] = n),
      if (menuItems.contains('center_approval'))
        _count(() => _centersApi.pendingCenters()).then((n) => updates['center_approval'] = n),
      if (menuItems.contains('am_loan_approval'))
        _count(() => _loansApi.pendingApprovals('Proposal')).then((n) => updates['am_loan_approval'] = n),
      if (menuItems.contains('disbursement'))
        _count(() => _loansApi.pendingDisbursement()).then((n) => updates['disbursement'] = n),
    ]);
    pendingCounts = updates;
    notifyListeners();
  }

  Future<int> _count<T>(Future<List<T>> Function() fetch) async {
    try {
      return (await fetch()).length;
    } catch (_) {
      return 0;
    }
  }

  Future<int> _sumCounts(List<Future<List<dynamic>> Function()> fetchers) async {
    final counts = await Future.wait<int>(fetchers.map((f) => _count(f)));
    return counts.fold<int>(0, (sum, c) => sum + c);
  }
}

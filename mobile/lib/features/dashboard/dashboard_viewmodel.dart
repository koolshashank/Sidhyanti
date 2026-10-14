import 'package:flutter/foundation.dart';

import '../../core/api/centers_api.dart';
import '../../core/api/collections_api.dart';
import '../../core/api/hr_api.dart';
import '../../core/api/loans_api.dart';
import '../../core/api/members_api.dart';
import '../../core/location/location_service.dart';
import '../../core/models/collection_due_item.dart';
import '../../core/models/paginated_result.dart';

class DashboardViewModel extends ChangeNotifier {
  final LocationService _locationService;
  final LoansApi _loansApi;
  final CentersApi _centersApi;
  final HrApi _hrApi;
  final CollectionsApi _collectionsApi;
  final MembersApi _membersApi;

  DashboardViewModel(
    this._locationService,
    this._loansApi,
    this._centersApi,
    this._hrApi,
    this._collectionsApi,
    this._membersApi,
  ) {
    checkLocation();
  }

  LocationStatus? locationStatus;
  bool locationChecking = false;

  /// Menu key -> how many items are waiting on this staff member's action
  /// right now (pending loan proposals, centers, leave requests, etc.) --
  /// shown as a small badge on that menu's DashboardCard so a BM/AM doesn't
  /// have to open every queue just to see if anything is there.
  Map<String, int> pendingCounts = {};

  /// Today's collection at a glance, shown on the dashboard header -- null
  /// until loaded, and left null for staff without collection access.
  TodayCollectionSummary? todaySummary;

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
  /// Sum of every approval-queue badge currently shown -- the dashboard's
  /// "Pending Approvals" quick-stat chip, so a BM/AM/CM sees one number
  /// without opening each queue.
  int get totalPendingApprovals =>
      (pendingCounts['bm_approval'] ?? 0) + (pendingCounts['center_approval'] ?? 0) + (pendingCounts['am_loan_approval'] ?? 0);

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
      if (menuItems.contains('client_details'))
        _totalCount(() => _membersApi.list(perPage: 1, activeLoan: true)).then((n) => updates['client_details'] = n),
      if (menuItems.contains('dormant_clients'))
        _totalCount(() => _membersApi.list(perPage: 1, dormant: true)).then((n) => updates['dormant_clients'] = n),
      if (menuItems.contains('flo_collection')) _loadTodaySummary(),
    ]);
    pendingCounts = updates;
    notifyListeners();
  }

  Future<void> _loadTodaySummary() async {
    try {
      todaySummary = await _collectionsApi.todaySummary();
    } catch (_) {
      // Leave it null -- the chip just doesn't render.
    }
  }

  Future<int> _count<T>(Future<List<T>> Function() fetch) async {
    try {
      return (await fetch()).length;
    } catch (_) {
      return 0;
    }
  }

  /// Like [_count], but for result sets too large to fetch in full just to
  /// know how many there are -- reads the paginated `total` instead of the
  /// page of items (the request itself still asks for perPage: 1, so almost
  /// nothing is transferred).
  Future<int> _totalCount(Future<PaginatedResult<dynamic>> Function() fetch) async {
    try {
      return (await fetch()).total;
    } catch (_) {
      return 0;
    }
  }

  Future<int> _sumCounts(List<Future<List<dynamic>> Function()> fetchers) async {
    final counts = await Future.wait<int>(fetchers.map((f) => _count(f)));
    return counts.fold<int>(0, (sum, c) => sum + c);
  }
}

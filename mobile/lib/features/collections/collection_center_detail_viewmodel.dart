import 'package:flutter/foundation.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/collections_api.dart';
import '../../core/models/collection_due_item.dart';

class CollectionCenterDetailViewModel extends ChangeNotifier {
  final CollectionsApi _collectionsApi;
  final int centerId;
  final DateTime dateFrom;
  final DateTime dateTo;

  CollectionCenterDetailViewModel(this._collectionsApi, this.centerId, this.dateFrom, this.dateTo) {
    load();
  }

  List<CollectionDueItem> items = [];
  CollectionsSummary? summary;
  bool loading = false;
  String? error;

  // Attendance gate -- only relevant when this center is being viewed for
  // today (the live meeting date); Yesterday/Last-7-days views skip it
  // entirely since you can't take attendance for a meeting already past.
  bool get isTodayView {
    final now = DateTime.now();
    bool sameDay(DateTime d) => d.year == now.year && d.month == now.month && d.day == now.day;
    return sameDay(dateFrom) && sameDay(dateTo);
  }

  List<AttendanceItem> attendanceItems = [];
  bool attendanceComplete = true;
  bool attendanceLoading = false;
  bool submittingAttendance = false;
  String? attendanceError;

  Future<void> load() async {
    loading = true;
    error = null;
    notifyListeners();
    try {
      if (isTodayView) {
        await _loadAttendance();
        if (!attendanceComplete) {
          return;
        }
      }
      final result = await _collectionsApi.due(
        dateFrom: dateFrom,
        dateTo: dateTo,
        centerId: centerId,
        perPage: 200,
      );
      items = result.page.items;
      summary = result.summary;
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      loading = false;
      notifyListeners();
    }
  }

  Future<void> _loadAttendance() async {
    attendanceLoading = true;
    notifyListeners();
    try {
      final result = await _collectionsApi.getAttendance(centerId: centerId, date: dateFrom);
      attendanceItems = result.items;
      attendanceComplete = result.isComplete;
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      attendanceLoading = false;
    }
  }

  void setAttendanceStatus(int loanId, String status) {
    final index = attendanceItems.indexWhere((i) => i.loanId == loanId);
    if (index == -1) return;
    attendanceItems[index] = attendanceItems[index].copyWith(status: status);
    notifyListeners();
  }

  void markAllPresent() {
    attendanceItems = attendanceItems.map((i) => i.copyWith(status: 'Present')).toList();
    notifyListeners();
  }

  Future<bool> submitAttendance() async {
    if (attendanceItems.any((i) => i.status == null)) {
      attendanceError = 'Please mark every client as Present or Absent';
      notifyListeners();
      return false;
    }
    submittingAttendance = true;
    attendanceError = null;
    notifyListeners();
    try {
      final result = await _collectionsApi.markAttendance(
        centerId: centerId,
        date: dateFrom,
        records: attendanceItems.map((i) => {'loan_id': i.loanId, 'status': i.status}).toList(),
      );
      attendanceComplete = result.isComplete;
      if (attendanceComplete) {
        await load();
      }
      return attendanceComplete;
    } on ApiException catch (e) {
      attendanceError = e.message;
      return false;
    } finally {
      submittingAttendance = false;
      notifyListeners();
    }
  }

  void markCollected(int scheduleId, double collectedAmount) {
    final index = items.indexWhere((i) => i.scheduleId == scheduleId);
    if (index == -1) return;
    final old = items[index];
    items[index] = CollectionDueItem(
      loanId: old.loanId,
      loanNo: old.loanNo,
      scheduleId: old.scheduleId,
      installmentNo: old.installmentNo,
      borrowerName: old.borrowerName,
      coBorrowerName: old.coBorrowerName,
      centerId: old.centerId,
      centerName: old.centerName,
      collectionDueDate: old.collectionDueDate,
      dueAmount: old.dueAmount,
      collectedAmount: collectedAmount,
      status: 'Collected',
      totalDisbursed: old.totalDisbursed,
      totalCollected: old.totalCollected + collectedAmount,
    );
    if (summary != null) {
      summary = CollectionsSummary(
        clientCount: summary!.clientCount,
        dueAmount: summary!.dueAmount,
        collectedAmount: summary!.collectedAmount + collectedAmount,
        balance: summary!.balance - collectedAmount,
      );
    }
    notifyListeners();
  }
}

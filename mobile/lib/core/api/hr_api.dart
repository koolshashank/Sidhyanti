import '../models/approval.dart';
import '../models/hr.dart';
import 'api_client.dart';
import 'endpoints.dart';

class HrApi {
  final ApiClient _client;

  HrApi(this._client);

  Future<AttendanceStatus> attendanceToday() async {
    final data = await _client.get(Endpoints.hrAttendanceToday);
    return AttendanceStatus.fromJson(data);
  }

  Future<void> checkIn({double? latitude, double? longitude}) async {
    await _client.post(Endpoints.hrCheckin, data: {
      'latitude': ?latitude,
      'longitude': ?longitude,
    });
  }

  Future<void> checkOut({double? latitude, double? longitude}) async {
    await _client.post(Endpoints.hrCheckout, data: {
      'latitude': ?latitude,
      'longitude': ?longitude,
    });
  }

  Future<List<AttendanceHistoryItem>> attendanceHistory() async {
    final data = await _client.get(Endpoints.hrAttendanceHistory);
    return (data['items'] as List<dynamic>).map((e) => AttendanceHistoryItem.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<List<LeaveRequestItem>> leaveRequests() async {
    final data = await _client.get(Endpoints.hrLeaveRequests);
    return (data['items'] as List<dynamic>).map((e) => LeaveRequestItem.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<void> createLeaveRequest({
    required String leaveType,
    required DateTime fromDate,
    required DateTime toDate,
    String? reason,
  }) async {
    String iso(DateTime d) => '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';
    await _client.post(Endpoints.hrLeaveRequestCreate, data: {
      'leave_type': leaveType,
      'from_date': iso(fromDate),
      'to_date': iso(toDate),
      if (reason != null && reason.isNotEmpty) 'reason': reason,
    });
  }

  /// Reviewer-facing queue: every Pending leave request from staff in the
  /// caller's branch. Requires the "approve_leave_request" permission.
  Future<List<PendingLeaveRequest>> pendingLeaveRequests() async {
    final data = await _client.get(Endpoints.hrLeaveRequestsPending);
    return (data['items'] as List<dynamic>).map((e) => PendingLeaveRequest.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<void> reviewLeaveRequest({
    required int leaveRequestId,
    required bool approve,
    String? remarks,
  }) async {
    await _client.post(Endpoints.hrLeaveRequestApprove, data: {
      'leave_request_id': leaveRequestId,
      'action': approve ? 'approve' : 'reject',
      if (remarks != null && remarks.isNotEmpty) 'remarks': remarks,
    });
  }
}

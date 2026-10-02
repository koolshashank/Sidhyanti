import 'dart:io';

import 'package:dio/dio.dart';

import '../models/approval.dart';
import '../models/center_review.dart';
import '../models/center_summary.dart';
import '../models/center_transfer.dart';
import '../models/lookup_option.dart';
import 'api_client.dart';
import 'endpoints.dart';

class NewCenterResult {
  final int centerId;
  final String centerCode;
  final String centerName;

  NewCenterResult({required this.centerId, required this.centerCode, required this.centerName});

  factory NewCenterResult.fromJson(Map<String, dynamic> json) => NewCenterResult(
        centerId: json['center_id'] as int,
        centerCode: json['center_code'] as String? ?? '',
        centerName: json['center_name'] as String? ?? '',
      );
}

class CentersApi {
  final ApiClient _client;

  CentersApi(this._client);

  Future<List<LookupOption>> lookupStates() async {
    final data = await _client.get(Endpoints.centersLookups);
    return (data['states'] as List<dynamic>).map((e) => LookupOption.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<List<CenterSummary>> myCenters() async {
    final data = await _client.get(Endpoints.centersList);
    return (data['centers'] as List<dynamic>).map((e) => CenterSummary.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<NewCenterResult> create({
    required String centerName,
    String? centerPlace,
    String? address,
    int? stateId,
    String? pincode,
    String? meetingDay,
    String? meetingTime,
    String? contactPerson,
    String? contactNo,
    double? latitude,
    double? longitude,
  }) async {
    final data = await _client.post(Endpoints.centersCreate, data: {
      'center_name': centerName,
      if (centerPlace != null && centerPlace.isNotEmpty) 'center_place': centerPlace,
      if (address != null && address.isNotEmpty) 'address': address,
      // ignore: use_null_aware_elements
      if (stateId != null) 'state_id': stateId,
      if (pincode != null && pincode.isNotEmpty) 'pincode': pincode,
      if (meetingDay != null && meetingDay.isNotEmpty) 'meeting_day': meetingDay,
      if (meetingTime != null && meetingTime.isNotEmpty) 'meeting_time': meetingTime,
      if (contactPerson != null && contactPerson.isNotEmpty) 'contact_person': contactPerson,
      if (contactNo != null && contactNo.isNotEmpty) 'contact_no': contactNo,
      // ignore: use_null_aware_elements
      if (latitude != null) 'latitude': latitude,
      // ignore: use_null_aware_elements
      if (longitude != null) 'longitude': longitude,
    });
    return NewCenterResult.fromJson(data);
  }

  Future<void> submitCgt({required int centerId, DateTime? cgtDate, String? remarks, File? photo}) async {
    final formData = FormData.fromMap({
      'center_id': centerId,
      if (cgtDate != null)
        'cgt_date':
            '${cgtDate.year.toString().padLeft(4, '0')}-${cgtDate.month.toString().padLeft(2, '0')}-${cgtDate.day.toString().padLeft(2, '0')}',
      if (remarks != null && remarks.isNotEmpty) 'remarks': remarks,
      if (photo != null) 'document': await MultipartFile.fromFile(photo.path, filename: photo.path.split('/').last),
    });
    await _client.postMultipart(Endpoints.centersCgtCreate, formData);
  }

  /// Reviewer-facing queue of centers awaiting Branch Manager approval.
  Future<List<PendingCenterItem>> pendingCenters() async {
    final data = await _client.get(Endpoints.centersPending);
    return (data['items'] as List<dynamic>).map((e) => PendingCenterItem.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<void> reviewCenter({
    required int centerId,
    required bool approve,
    String? remarks,
  }) async {
    await _client.post(Endpoints.centersApprove, data: {
      'center_id': centerId,
      'action': approve ? 'approve' : 'reject',
      if (remarks != null && remarks.isNotEmpty) 'remarks': remarks,
    });
  }

  /// Full editable detail for a pending Center -- the BM/AM's "Review
  /// Center" screen.
  Future<CenterReviewDetail> centerReviewDetail(int centerId) async {
    final data = await _client.get(Endpoints.centersDetail, query: {'center_id': centerId});
    return CenterReviewDetail.fromJson(data);
  }

  /// Saves the reviewer's corrections to a center still under review --
  /// deliberately excludes latitude/longitude, which stay exactly as
  /// captured on-site when the center was created.
  Future<void> updateCenter({
    required int centerId,
    required String centerName,
    String? address,
    String? centerPlace,
    int? stateId,
    String? pincode,
    String? meetingDay,
    String? meetingTime,
    String? contactPerson,
    String? contactNo,
  }) async {
    await _client.post(Endpoints.centersUpdate, data: {
      'center_id': centerId,
      'center_name': centerName,
      if (address != null && address.isNotEmpty) 'address': address,
      if (centerPlace != null && centerPlace.isNotEmpty) 'center_place': centerPlace,
      // ignore: use_null_aware_elements
      if (stateId != null) 'state_id': stateId,
      if (pincode != null && pincode.isNotEmpty) 'pincode': pincode,
      if (meetingDay != null && meetingDay.isNotEmpty) 'meeting_day': meetingDay,
      if (meetingTime != null && meetingTime.isNotEmpty) 'meeting_time': meetingTime,
      if (contactPerson != null && contactPerson.isNotEmpty) 'contact_person': contactPerson,
      if (contactNo != null && contactNo.isNotEmpty) 'contact_no': contactNo,
    });
  }

  /// Every active center in the caller's branch (not just ones they
  /// personally manage) -- the picker for Center Transfer.
  Future<List<CenterForTransfer>> branchCenters() async {
    final data = await _client.get(Endpoints.centersBranchList);
    return (data['items'] as List<dynamic>).map((e) => CenterForTransfer.fromJson(e as Map<String, dynamic>)).toList();
  }

  /// Active staff in the caller's branch -- the "new officer" picker for
  /// Center Transfer.
  Future<List<BranchStaffOption>> branchStaff() async {
    final data = await _client.get(Endpoints.centersBranchStaff);
    return (data['items'] as List<dynamic>).map((e) => BranchStaffOption.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<void> transferCenter({required int centerId, required int newStaffId, String? remark}) async {
    await _client.post(Endpoints.centersTransfer, data: {
      'center_id': centerId,
      'new_staff_id': newStaffId,
      if (remark != null && remark.isNotEmpty) 'remark': remark,
    });
  }
}

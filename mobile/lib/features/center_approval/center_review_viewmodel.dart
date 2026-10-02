import 'package:flutter/foundation.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/centers_api.dart';
import '../../core/models/center_review.dart';
import '../../core/models/lookup_option.dart';

class CenterReviewViewModel extends ChangeNotifier {
  final CentersApi _centersApi;
  final int centerId;

  CenterReviewViewModel(this._centersApi, this.centerId) {
    _load();
  }

  bool loading = true;
  String? loadError;
  CenterReviewDetail? detail;
  List<LookupOption> states = [];

  bool saving = false;
  String? saveError;
  bool deciding = false;
  String? decideError;

  Future<void> _load() async {
    loading = true;
    loadError = null;
    notifyListeners();
    try {
      final results = await Future.wait([_centersApi.centerReviewDetail(centerId), _centersApi.lookupStates()]);
      detail = results[0] as CenterReviewDetail;
      states = results[1] as List<LookupOption>;
    } on ApiException catch (e) {
      loadError = e.message;
    } finally {
      loading = false;
      notifyListeners();
    }
  }

  /// Saves the reviewer's corrections. Returns true on success.
  Future<bool> saveChanges({
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
    saving = true;
    saveError = null;
    notifyListeners();
    try {
      await _centersApi.updateCenter(
        centerId: centerId,
        centerName: centerName,
        address: address,
        centerPlace: centerPlace,
        stateId: stateId,
        pincode: pincode,
        meetingDay: meetingDay,
        meetingTime: meetingTime,
        contactPerson: contactPerson,
        contactNo: contactNo,
      );
      return true;
    } on ApiException catch (e) {
      saveError = e.message;
      return false;
    } finally {
      saving = false;
      notifyListeners();
    }
  }

  /// Approves or rejects. Returns true on success.
  Future<bool> decide({required bool approve, String? remarks}) async {
    deciding = true;
    decideError = null;
    notifyListeners();
    try {
      await _centersApi.reviewCenter(centerId: centerId, approve: approve, remarks: remarks);
      return true;
    } on ApiException catch (e) {
      decideError = e.message;
      return false;
    } finally {
      deciding = false;
      notifyListeners();
    }
  }
}

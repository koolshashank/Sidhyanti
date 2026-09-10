import 'package:flutter/foundation.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/members_api.dart';
import '../../core/models/lookup_option.dart';

class ClientSourcingViewModel extends ChangeNotifier {
  final MembersApi _membersApi;

  ClientSourcingViewModel(this._membersApi) {
    _loadLookups();
  }

  bool lookupsLoading = true;
  String? lookupsError;
  List<LookupOption> leadSources = [];
  List<LookupOption> occupations = [];
  List<LookupOption> educations = [];
  List<LookupOption> religions = [];
  List<LookupOption> castes = [];
  List<LookupOption> houseTypes = [];
  List<LookupOption> states = [];

  bool districtsLoading = false;
  List<LookupOption> districts = [];
  int? _loadedForStateId;

  bool submitting = false;
  String? submitError;

  Future<void> _loadLookups() async {
    lookupsLoading = true;
    lookupsError = null;
    notifyListeners();
    try {
      final result = await _membersApi.lookups();
      leadSources = result.leadSources;
      occupations = result.occupations;
      educations = result.educations;
      religions = result.religions;
      castes = result.castes;
      houseTypes = result.houseTypes;
      states = result.states;
    } on ApiException catch (e) {
      lookupsError = e.message;
    } finally {
      lookupsLoading = false;
      notifyListeners();
    }
  }

  Future<void> loadDistricts(int stateId) async {
    if (_loadedForStateId == stateId) return;
    districtsLoading = true;
    districts = [];
    notifyListeners();
    try {
      districts = await _membersApi.districts(stateId);
      _loadedForStateId = stateId;
    } on ApiException {
      // District dropdown just stays empty -- the field validator will
      // catch it if the user can't pick one.
    } finally {
      districtsLoading = false;
      notifyListeners();
    }
  }

  Future<NewLeadResult?> submit({
    required String firstName,
    String? lastName,
    String? fatherName,
    String? motherName,
    String? maritalStatus,
    DateTime? dob,
    required String mobile,
    int? educationId,
    String? gender,
    int? noOfDependents,
    int? occupationId,
    int? religionId,
    int? casteId,
    String? address,
    int? stateId,
    int? districtId,
    String? pincode,
    int? houseTypeId,
    String? aadharNo,
    String? middleName,
    String? landmark,
    String? panNo,
    String? voterId,
    int? centerId,
    int? leadSourceId,
  }) async {
    submitting = true;
    submitError = null;
    notifyListeners();
    try {
      return await _membersApi.create(
        firstName: firstName,
        lastName: lastName,
        fatherName: fatherName,
        motherName: motherName,
        maritalStatus: maritalStatus,
        dob: dob,
        mobile: mobile,
        educationId: educationId,
        gender: gender,
        noOfDependents: noOfDependents,
        occupationId: occupationId,
        religionId: religionId,
        casteId: casteId,
        address: address,
        stateId: stateId,
        districtId: districtId,
        pincode: pincode,
        houseTypeId: houseTypeId,
        aadharNo: aadharNo,
        middleName: middleName,
        landmark: landmark,
        panNo: panNo,
        voterId: voterId,
        centerId: centerId,
        leadSourceId: leadSourceId,
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

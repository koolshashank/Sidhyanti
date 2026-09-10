import 'package:flutter/foundation.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/coborrowers_api.dart';
import '../../core/api/members_api.dart';
import '../../core/models/lookup_option.dart';

class CoBorrowerViewModel extends ChangeNotifier {
  final MembersApi _membersApi;
  final CoborrowersApi _coborrowersApi;

  CoBorrowerViewModel(this._membersApi, this._coborrowersApi) {
    _loadLookups();
  }

  bool lookupsLoading = true;
  String? lookupsError;
  List<LookupOption> occupations = [];
  List<LookupOption> educations = [];
  List<LookupOption> religions = [];
  List<LookupOption> castes = [];
  List<LookupOption> houseTypes = [];

  bool submitting = false;
  String? submitError;

  Future<void> _loadLookups() async {
    lookupsLoading = true;
    lookupsError = null;
    notifyListeners();
    try {
      final result = await _membersApi.lookups();
      occupations = result.occupations;
      educations = result.educations;
      religions = result.religions;
      castes = result.castes;
      houseTypes = result.houseTypes;
    } on ApiException catch (e) {
      lookupsError = e.message;
    } finally {
      lookupsLoading = false;
      notifyListeners();
    }
  }

  Future<NewCoBorrowerResult?> submit({
    required int memberId,
    String? firstName,
    String? lastName,
    String? relationToMember,
    String? motherName,
    String? maritalStatus,
    DateTime? dob,
    String? mobile,
    int? educationId,
    String? gender,
    int? occupationId,
    int? religionId,
    int? casteId,
    int? houseTypeId,
    String? address,
    String? villageCity,
    String? pincode,
    String? aadharNo,
    String? middleName,
    String? panNo,
    String? voterId,
    String? rationCardNo,
    String? dlNo,
    String? electricityBillNo,
  }) async {
    submitting = true;
    submitError = null;
    notifyListeners();
    try {
      return await _coborrowersApi.create(
        memberId: memberId,
        firstName: firstName,
        lastName: lastName,
        relationToMember: relationToMember,
        motherName: motherName,
        maritalStatus: maritalStatus,
        dob: dob,
        mobile: mobile,
        educationId: educationId,
        gender: gender,
        occupationId: occupationId,
        religionId: religionId,
        casteId: casteId,
        houseTypeId: houseTypeId,
        address: address,
        villageCity: villageCity,
        pincode: pincode,
        aadharNo: aadharNo,
        middleName: middleName,
        panNo: panNo,
        voterId: voterId,
        rationCardNo: rationCardNo,
        dlNo: dlNo,
        electricityBillNo: electricityBillNo,
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

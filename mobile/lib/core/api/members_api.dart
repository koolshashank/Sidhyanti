import 'dart:io';

import 'package:dio/dio.dart';

import '../models/lookup_option.dart';
import '../models/member.dart';
import '../models/paginated_result.dart';
import 'api_client.dart';
import 'endpoints.dart';

class MemberLookups {
  final List<LookupOption> centers;
  final List<LookupOption> leadSources;
  final List<LookupOption> occupations;
  final List<LookupOption> educations;
  final List<LookupOption> religions;
  final List<LookupOption> castes;
  final List<LookupOption> houseTypes;
  final List<LookupOption> states;
  final List<LookupOption> kycDocTypes;

  MemberLookups({
    required this.centers,
    required this.leadSources,
    required this.occupations,
    required this.educations,
    required this.religions,
    required this.castes,
    required this.houseTypes,
    required this.states,
    required this.kycDocTypes,
  });
}

class NewLeadResult {
  final int memberId;
  final String fullName;

  NewLeadResult({required this.memberId, required this.fullName});

  factory NewLeadResult.fromJson(Map<String, dynamic> json) =>
      NewLeadResult(memberId: json['member_id'] as int, fullName: json['full_name'] as String? ?? '');
}

class MembersApi {
  final ApiClient _client;

  MembersApi(this._client);

  Future<PaginatedResult<MemberSummary>> list({int page = 1, int perPage = 20, String? q}) async {
    final data = await _client.get(Endpoints.membersList, query: {
      'page': page,
      'per_page': perPage,
      if (q != null && q.isNotEmpty) 'q': q,
    });
    return PaginatedResult.fromJson(data, MemberSummary.fromJson);
  }

  Future<MemberDetail> detail(int memberId) async {
    final data = await _client.get(Endpoints.membersDetail, query: {'id': memberId});
    return MemberDetail.fromJson(data);
  }

  Future<List<MemberDocument>> documents(int memberId) async {
    final data = await _client.get(Endpoints.membersDocuments, query: {'member_id': memberId});
    return (data['items'] as List<dynamic>).map((e) => MemberDocument.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<List<MemberRepaymentItem>> repaymentHistory(int memberId) async {
    final data = await _client.get(Endpoints.membersRepaymentHistory, query: {'member_id': memberId});
    return (data['items'] as List<dynamic>).map((e) => MemberRepaymentItem.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<List<MemberStageHistoryItem>> stageHistory(int memberId) async {
    final data = await _client.get(Endpoints.membersStageHistory, query: {'member_id': memberId});
    return (data['items'] as List<dynamic>).map((e) => MemberStageHistoryItem.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<IncompleteJourney?> incompleteJourney() async {
    final data = await _client.get(Endpoints.membersIncompleteJourney);
    if (data['has_incomplete'] != true) return null;
    return IncompleteJourney.fromJson(data);
  }

  Future<void> discardJourney(int memberId) async {
    await _client.post(Endpoints.membersDiscardJourney, data: {'member_id': memberId});
  }

  List<LookupOption> _options(Map<String, dynamic> data, String key) =>
      (data[key] as List<dynamic>).map((e) => LookupOption.fromJson(e as Map<String, dynamic>)).toList();

  Future<MemberLookups> lookups() async {
    final data = await _client.get(Endpoints.membersLookups);
    return MemberLookups(
      centers: _options(data, 'centers'),
      leadSources: _options(data, 'lead_sources'),
      occupations: _options(data, 'occupations'),
      educations: _options(data, 'educations'),
      religions: _options(data, 'religions'),
      castes: _options(data, 'castes'),
      houseTypes: _options(data, 'house_types'),
      states: _options(data, 'states'),
      kycDocTypes: _options(data, 'kyc_doc_types'),
    );
  }

  Future<List<LookupOption>> districts(int stateId) async {
    final data = await _client.get(Endpoints.membersDistricts, query: {'state_id': stateId});
    return _options(data, 'districts');
  }

  /// Only [firstName] and [mobile] are truly required (matches the Member
  /// table's NOT NULL columns) -- every other field is optional so testers
  /// aren't forced to fill the whole form on every dev-time submission.
  Future<NewLeadResult> create({
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
    final data = await _client.post(Endpoints.membersCreate, data: {
      'first_name': firstName,
      'mobile': mobile,
      if (lastName != null && lastName.isNotEmpty) 'last_name': lastName,
      if (fatherName != null && fatherName.isNotEmpty) 'father_name': fatherName,
      if (motherName != null && motherName.isNotEmpty) 'mother_name': motherName,
      'marital_status': ?maritalStatus,
      if (dob != null)
        'dob': '${dob.year.toString().padLeft(4, '0')}-${dob.month.toString().padLeft(2, '0')}-${dob.day.toString().padLeft(2, '0')}',
      'education_id': ?educationId,
      'gender': ?gender,
      'no_of_dependents': ?noOfDependents,
      'occupation_id': ?occupationId,
      'religion_id': ?religionId,
      'caste_id': ?casteId,
      if (address != null && address.isNotEmpty) 'address': address,
      'state_id': ?stateId,
      'district_id': ?districtId,
      if (pincode != null && pincode.isNotEmpty) 'pincode': pincode,
      'house_type_id': ?houseTypeId,
      if (aadharNo != null && aadharNo.isNotEmpty) 'aadhar_no': aadharNo,
      if (middleName != null && middleName.isNotEmpty) 'middle_name': middleName,
      if (landmark != null && landmark.isNotEmpty) 'landmark': landmark,
      if (panNo != null && panNo.isNotEmpty) 'pan_no': panNo,
      if (voterId != null && voterId.isNotEmpty) 'voter_id': voterId,
      'center_id': ?centerId,
      'lead_source_id': ?leadSourceId,
    });
    return NewLeadResult.fromJson(data);
  }

  /// Uploads one KYC document/photo for a member (or, if [coBorrowerId] is
  /// given, for that co-borrower instead). A second upload for the same
  /// (member, docTypeId, coBorrowerId) replaces the previous one.
  Future<String> uploadKycDocument({
    required int memberId,
    required int docTypeId,
    required File file,
    int? coBorrowerId,
    String? docNumber,
  }) async {
    final formData = FormData.fromMap({
      'member_id': memberId,
      'doc_type_id': docTypeId,
      'coborrower_id': ?coBorrowerId,
      if (docNumber != null && docNumber.isNotEmpty) 'doc_number': docNumber,
      'document': await MultipartFile.fromFile(file.path, filename: file.path.split('/').last),
    });
    final data = await _client.postMultipart(Endpoints.membersKycUpload, formData);
    return data['file_url'] as String;
  }
}

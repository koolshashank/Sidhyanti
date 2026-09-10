import 'api_client.dart';
import 'endpoints.dart';

class NewCoBorrowerResult {
  final int coBorrowerId;
  final int memberId;

  NewCoBorrowerResult({required this.coBorrowerId, required this.memberId});

  factory NewCoBorrowerResult.fromJson(Map<String, dynamic> json) => NewCoBorrowerResult(
        coBorrowerId: json['coborrower_id'] as int,
        memberId: json['member_id'] as int,
      );
}

class CoborrowersApi {
  final ApiClient _client;

  CoborrowersApi(this._client);

  /// Only [memberId] is truly required (Co_Borrower's only NOT NULL column
  /// besides its own PK) -- every other field is optional so testers aren't
  /// forced to fill the whole form on every dev-time submission.
  Future<NewCoBorrowerResult> create({
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
    final data = await _client.post(Endpoints.coborrowersCreate, data: {
      'member_id': memberId,
      if (firstName != null && firstName.isNotEmpty) 'first_name': firstName,
      if (lastName != null && lastName.isNotEmpty) 'last_name': lastName,
      'relation_to_member': ?relationToMember,
      if (motherName != null && motherName.isNotEmpty) 'mother_name': motherName,
      'marital_status': ?maritalStatus,
      if (dob != null)
        'dob': '${dob.year.toString().padLeft(4, '0')}-${dob.month.toString().padLeft(2, '0')}-${dob.day.toString().padLeft(2, '0')}',
      if (mobile != null && mobile.isNotEmpty) 'mobile': mobile,
      'education_id': ?educationId,
      'gender': ?gender,
      'occupation_id': ?occupationId,
      'religion_id': ?religionId,
      'caste_id': ?casteId,
      'house_type_id': ?houseTypeId,
      if (address != null && address.isNotEmpty) 'address': address,
      if (villageCity != null && villageCity.isNotEmpty) 'village_city': villageCity,
      if (pincode != null && pincode.isNotEmpty) 'pincode': pincode,
      if (aadharNo != null && aadharNo.isNotEmpty) 'aadhar_no': aadharNo,
      if (middleName != null && middleName.isNotEmpty) 'middle_name': middleName,
      if (panNo != null && panNo.isNotEmpty) 'pan_no': panNo,
      if (voterId != null && voterId.isNotEmpty) 'voter_id': voterId,
      if (rationCardNo != null && rationCardNo.isNotEmpty) 'ration_card_no': rationCardNo,
      if (dlNo != null && dlNo.isNotEmpty) 'dl_no': dlNo,
      if (electricityBillNo != null && electricityBillNo.isNotEmpty) 'electricity_bill_no': electricityBillNo,
    });
    return NewCoBorrowerResult.fromJson(data);
  }
}

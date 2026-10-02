class CenterReviewDetail {
  final int centerId;
  final String centerCode;
  final String approvalStatus;
  final String? branchName;
  final String? managedByName;
  final String centerName;
  final String? address;
  final String? centerPlace;
  final int? stateId;
  final String? pincode;
  final String? meetingDay;
  final String? meetingTime;
  final String? contactPerson;
  final String? contactNo;

  /// Captured live on-site when the center was created -- display only,
  /// never sent back by centers/update.php.
  final double? latitude;
  final double? longitude;

  CenterReviewDetail({
    required this.centerId,
    required this.centerCode,
    required this.approvalStatus,
    required this.branchName,
    required this.managedByName,
    required this.centerName,
    required this.address,
    required this.centerPlace,
    required this.stateId,
    required this.pincode,
    required this.meetingDay,
    required this.meetingTime,
    required this.contactPerson,
    required this.contactNo,
    required this.latitude,
    required this.longitude,
  });

  factory CenterReviewDetail.fromJson(Map<String, dynamic> json) => CenterReviewDetail(
        centerId: json['center_id'] as int,
        centerCode: json['center_code'] as String? ?? '',
        approvalStatus: json['approval_status'] as String? ?? '',
        branchName: json['branch_name'] as String?,
        managedByName: json['managed_by_name'] as String?,
        centerName: json['center_name'] as String? ?? '',
        address: json['address'] as String?,
        centerPlace: json['center_place'] as String?,
        stateId: json['state_id'] as int?,
        pincode: json['pincode'] as String?,
        meetingDay: json['meeting_day'] as String?,
        meetingTime: json['meeting_time'] as String?,
        contactPerson: json['contact_person'] as String?,
        contactNo: json['contact_no'] as String?,
        latitude: (json['latitude'] as num?)?.toDouble(),
        longitude: (json['longitude'] as num?)?.toDouble(),
      );
}

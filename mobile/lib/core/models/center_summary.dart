class CenterSummary {
  final int id;
  final String name;
  final String? meetingDay;
  final String? meetingTime;
  final String? address;
  final String? place;
  final double? latitude;
  final double? longitude;
  final bool cgtDone;
  final DateTime? cgtDate;
  final String? cgtImageUrl;
  final String? cgtRemarks;
  final String? cgtConductedBy;

  CenterSummary({
    required this.id,
    required this.name,
    required this.meetingDay,
    required this.meetingTime,
    required this.address,
    required this.place,
    required this.latitude,
    required this.longitude,
    required this.cgtDone,
    required this.cgtDate,
    required this.cgtImageUrl,
    required this.cgtRemarks,
    required this.cgtConductedBy,
  });

  bool get hasLocation => latitude != null && longitude != null;

  factory CenterSummary.fromJson(Map<String, dynamic> json) => CenterSummary(
        id: json['id'] as int,
        name: json['name'] as String? ?? '',
        meetingDay: json['meeting_day'] as String?,
        meetingTime: json['meeting_time'] as String?,
        address: json['address'] as String?,
        place: json['place'] as String?,
        latitude: (json['latitude'] as num?)?.toDouble(),
        longitude: (json['longitude'] as num?)?.toDouble(),
        cgtDone: json['cgt_done'] as bool? ?? false,
        cgtDate: json['cgt_date'] != null ? DateTime.tryParse(json['cgt_date'] as String) : null,
        cgtImageUrl: json['cgt_image_url'] as String?,
        cgtRemarks: json['cgt_remarks'] as String?,
        cgtConductedBy: json['cgt_conducted_by'] as String?,
      );
}

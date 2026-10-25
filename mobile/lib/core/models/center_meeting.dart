/// A center scheduled to meet today (Center.MeetingDay matches today),
/// with this officer's own geo check-in status for today, if any -- see
/// api/v1/centers/todays_meetings.php.
class TodaysMeetingCenter {
  final int centerId;
  final String centerName;
  final String? branchName;
  final String? meetingTime;
  final DateTime? checkedInAt;
  final bool? withinGeofence;

  TodaysMeetingCenter({
    required this.centerId,
    required this.centerName,
    required this.branchName,
    required this.meetingTime,
    required this.checkedInAt,
    required this.withinGeofence,
  });

  bool get isCheckedIn => checkedInAt != null;

  factory TodaysMeetingCenter.fromJson(Map<String, dynamic> json) => TodaysMeetingCenter(
        centerId: json['center_id'] as int,
        centerName: json['center_name'] as String? ?? '',
        branchName: json['branch_name'] as String?,
        meetingTime: json['meeting_time'] as String?,
        checkedInAt: json['checked_in_at'] != null ? DateTime.tryParse(json['checked_in_at'] as String) : null,
        withinGeofence: json['within_geofence'] as bool?,
      );
}

class CenterCheckinResult {
  final int checkinId;
  final double? distanceMeters;
  final bool? withinGeofence;

  CenterCheckinResult({required this.checkinId, required this.distanceMeters, required this.withinGeofence});

  factory CenterCheckinResult.fromJson(Map<String, dynamic> json) => CenterCheckinResult(
        checkinId: json['checkin_id'] as int,
        distanceMeters: (json['distance_meters'] as num?)?.toDouble(),
        withinGeofence: json['within_geofence'] as bool?,
      );
}

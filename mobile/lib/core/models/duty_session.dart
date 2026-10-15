/// A single GPS fix queued for upload -- captured locally, held in
/// [LocationQueueStore] until it's successfully sent to tracking/ping.php.
class LocationPoint {
  final double lat;
  final double lng;
  final double? accuracy;
  final double? speed;
  final DateTime capturedAt;

  LocationPoint({
    required this.lat,
    required this.lng,
    this.accuracy,
    this.speed,
    required this.capturedAt,
  });

  Map<String, dynamic> toJson() => {
        'lat': lat,
        'lng': lng,
        if (accuracy != null) 'accuracy': accuracy,
        if (speed != null) 'speed': speed,
        'captured_at': capturedAt.toIso8601String(),
      };

  factory LocationPoint.fromJson(Map<String, dynamic> json) => LocationPoint(
        lat: (json['lat'] as num).toDouble(),
        lng: (json['lng'] as num).toDouble(),
        accuracy: (json['accuracy'] as num?)?.toDouble(),
        speed: (json['speed'] as num?)?.toDouble(),
        capturedAt: DateTime.parse(json['captured_at'] as String),
      );
}

/// Returned by duty_start.php / duty_end.php -- the caller's own active
/// (or just-closed) Duty_Session.
class DutySessionInfo {
  final int sessionId;
  final DateTime startTime;
  final bool resumed;

  DutySessionInfo({required this.sessionId, required this.startTime, this.resumed = false});

  factory DutySessionInfo.fromJson(Map<String, dynamic> json) => DutySessionInfo(
        sessionId: json['session_id'] as int,
        startTime: DateTime.parse(json['start_time'] as String),
        resumed: json['resumed'] as bool? ?? false,
      );
}

/// One row of tracking/live_map.php -- a staff member currently on duty and
/// their last known position.
class StaffLiveLocation {
  final int staffId;
  final String fullName;
  final String? phone;
  final String? branchName;
  final int sessionId;
  final double latitude;
  final double longitude;
  final double? accuracy;
  final DateTime capturedAt;

  StaffLiveLocation({
    required this.staffId,
    required this.fullName,
    this.phone,
    this.branchName,
    required this.sessionId,
    required this.latitude,
    required this.longitude,
    this.accuracy,
    required this.capturedAt,
  });

  factory StaffLiveLocation.fromJson(Map<String, dynamic> json) => StaffLiveLocation(
        staffId: json['staff_id'] as int,
        fullName: json['full_name'] as String? ?? '',
        phone: json['phone'] as String?,
        branchName: json['branch_name'] as String?,
        sessionId: json['session_id'] as int,
        latitude: (json['latitude'] as num).toDouble(),
        longitude: (json['longitude'] as num).toDouble(),
        accuracy: (json['accuracy'] as num?)?.toDouble(),
        capturedAt: DateTime.parse(json['captured_at'] as String),
      );
}

/// A single filtered point in tracking/route.php's response.
class RoutePoint {
  final double latitude;
  final double longitude;
  final double? accuracy;
  final double? speed;
  final DateTime capturedAt;

  RoutePoint({
    required this.latitude,
    required this.longitude,
    this.accuracy,
    this.speed,
    required this.capturedAt,
  });

  factory RoutePoint.fromJson(Map<String, dynamic> json) => RoutePoint(
        latitude: (json['latitude'] as num).toDouble(),
        longitude: (json['longitude'] as num).toDouble(),
        accuracy: (json['accuracy'] as num?)?.toDouble(),
        speed: (json['speed'] as num?)?.toDouble(),
        capturedAt: DateTime.parse(json['captured_at'] as String),
      );
}

/// tracking/route.php's full response -- the filtered route plus the
/// server-computed distance/duration summary.
class StaffRouteResult {
  final List<RoutePoint> points;
  final DateTime? startTime;
  final DateTime? endTime;
  final int durationMinutes;
  final double totalDistanceKm;

  StaffRouteResult({
    required this.points,
    this.startTime,
    this.endTime,
    required this.durationMinutes,
    required this.totalDistanceKm,
  });

  factory StaffRouteResult.fromJson(Map<String, dynamic> json) {
    final summary = json['summary'] as Map<String, dynamic>;
    return StaffRouteResult(
      points: (json['points'] as List<dynamic>)
          .map((e) => RoutePoint.fromJson(e as Map<String, dynamic>))
          .toList(),
      startTime: summary['start_time'] != null ? DateTime.parse(summary['start_time'] as String) : null,
      endTime: summary['end_time'] != null ? DateTime.parse(summary['end_time'] as String) : null,
      durationMinutes: summary['duration_minutes'] as int? ?? 0,
      totalDistanceKm: (summary['total_distance_km'] as num?)?.toDouble() ?? 0.0,
    );
  }
}

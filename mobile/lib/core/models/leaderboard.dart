class LeaderboardCollectorRow {
  final int staffId;
  final String fullName;
  final String? branchName;
  final double collectedAmount;
  final bool isMe;

  LeaderboardCollectorRow({
    required this.staffId,
    required this.fullName,
    required this.branchName,
    required this.collectedAmount,
    required this.isMe,
  });

  factory LeaderboardCollectorRow.fromJson(Map<String, dynamic> json) => LeaderboardCollectorRow(
        staffId: json['staff_id'] as int,
        fullName: json['full_name'] as String? ?? '',
        branchName: json['branch_name'] as String?,
        collectedAmount: (json['collected_amount'] as num?)?.toDouble() ?? 0,
        isMe: json['is_me'] as bool? ?? false,
      );
}

class LeaderboardAttendanceRow {
  final int staffId;
  final String fullName;
  final String? branchName;
  final int daysPresent;
  final bool isMe;

  LeaderboardAttendanceRow({
    required this.staffId,
    required this.fullName,
    required this.branchName,
    required this.daysPresent,
    required this.isMe,
  });

  factory LeaderboardAttendanceRow.fromJson(Map<String, dynamic> json) => LeaderboardAttendanceRow(
        staffId: json['staff_id'] as int,
        fullName: json['full_name'] as String? ?? '',
        branchName: json['branch_name'] as String?,
        daysPresent: json['days_present'] as int? ?? 0,
        isMe: json['is_me'] as bool? ?? false,
      );
}

class Leaderboard {
  final List<LeaderboardCollectorRow> topCollectors;
  final List<LeaderboardAttendanceRow> topAttendance;

  Leaderboard({required this.topCollectors, required this.topAttendance});

  factory Leaderboard.fromJson(Map<String, dynamic> json) => Leaderboard(
        topCollectors: (json['top_collectors'] as List<dynamic>? ?? [])
            .map((e) => LeaderboardCollectorRow.fromJson(e as Map<String, dynamic>))
            .toList(),
        topAttendance: (json['top_attendance'] as List<dynamic>? ?? [])
            .map((e) => LeaderboardAttendanceRow.fromJson(e as Map<String, dynamic>))
            .toList(),
      );
}

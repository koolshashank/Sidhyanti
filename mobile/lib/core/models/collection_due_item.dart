class CollectionDueItem {
  final int loanId;
  final String loanNo;
  final int scheduleId;
  final int installmentNo;
  final String borrowerName;
  final String? coBorrowerName;
  final int? centerId;
  final String? centerName;
  final DateTime? collectionDueDate;
  final double dueAmount;
  final double collectedAmount;
  final String status; // Pending | Collected | Preclosed | Overdue
  final double totalDisbursed;
  final double totalCollected;
  // Arrear-only fields (null for regular Collection-tab items).
  final double? arrearAmount;
  final int? overdueCount;
  final int? daysOverdue;

  CollectionDueItem({
    required this.loanId,
    required this.loanNo,
    required this.scheduleId,
    required this.installmentNo,
    required this.borrowerName,
    required this.coBorrowerName,
    required this.centerId,
    required this.centerName,
    required this.collectionDueDate,
    required this.dueAmount,
    required this.collectedAmount,
    required this.status,
    this.totalDisbursed = 0,
    this.totalCollected = 0,
    this.arrearAmount,
    this.overdueCount,
    this.daysOverdue,
  });

  bool get isPending => status == 'Pending' || status == 'Overdue';
  double get remaining => totalDisbursed - totalCollected;

  factory CollectionDueItem.fromJson(Map<String, dynamic> json) => CollectionDueItem(
        loanId: json['loan_id'] as int,
        loanNo: json['loan_no'] as String? ?? '',
        scheduleId: json['schedule_id'] as int? ?? 0,
        installmentNo: json['installment_no'] as int? ?? 0,
        borrowerName: json['borrower_name'] as String? ?? '',
        coBorrowerName: json['co_borrower_name'] as String?,
        centerId: json['center_id'] as int?,
        centerName: json['center_name'] as String?,
        collectionDueDate:
            json['collection_due_date'] != null ? DateTime.tryParse(json['collection_due_date'] as String) : null,
        dueAmount: (json['due_amount'] as num?)?.toDouble() ?? 0,
        collectedAmount: (json['collected_amount'] as num?)?.toDouble() ?? 0,
        status: json['status'] as String? ?? 'Pending',
        totalDisbursed: (json['total_disbursed'] as num?)?.toDouble() ?? 0,
        totalCollected: (json['total_collected'] as num?)?.toDouble() ?? 0,
        arrearAmount: (json['arrear_amount'] as num?)?.toDouble(),
        overdueCount: json['overdue_count'] as int?,
        daysOverdue: json['days_overdue'] as int?,
      );
}

class AttendanceItem {
  final int loanId;
  final String borrowerName;
  final String? coBorrowerName;
  final double dueAmount;
  final String? status; // 'Present' | 'Absent' | null (not yet marked)

  AttendanceItem({
    required this.loanId,
    required this.borrowerName,
    required this.coBorrowerName,
    required this.dueAmount,
    required this.status,
  });

  factory AttendanceItem.fromJson(Map<String, dynamic> json) => AttendanceItem(
        loanId: json['loan_id'] as int,
        borrowerName: json['borrower_name'] as String? ?? '',
        coBorrowerName: json['co_borrower_name'] as String?,
        dueAmount: (json['due_amount'] as num?)?.toDouble() ?? 0,
        status: json['status'] as String?,
      );

  AttendanceItem copyWith({String? status}) => AttendanceItem(
        loanId: loanId,
        borrowerName: borrowerName,
        coBorrowerName: coBorrowerName,
        dueAmount: dueAmount,
        status: status ?? this.status,
      );
}

class AttendanceResult {
  final List<AttendanceItem> items;
  final int totalDue;
  final int markedCount;
  final bool isComplete;

  AttendanceResult({required this.items, required this.totalDue, required this.markedCount, required this.isComplete});

  factory AttendanceResult.fromJson(Map<String, dynamic> json) => AttendanceResult(
        items: (json['items'] as List<dynamic>? ?? []).map((e) => AttendanceItem.fromJson(e as Map<String, dynamic>)).toList(),
        totalDue: json['total_due'] as int? ?? 0,
        markedCount: json['marked_count'] as int? ?? 0,
        isComplete: json['is_complete'] as bool? ?? true,
      );
}

class CollectionCenterSummary {
  final int? centerId;
  final String? centerName;
  final String? branchName;
  final String? meetingDay;
  final String? meetingTime;
  final int clientCount;
  final double target;
  final double received;
  final double balance;
  final double percent;

  CollectionCenterSummary({
    required this.centerId,
    required this.centerName,
    required this.branchName,
    required this.meetingDay,
    required this.meetingTime,
    required this.clientCount,
    required this.target,
    required this.received,
    required this.balance,
    required this.percent,
  });

  factory CollectionCenterSummary.fromJson(Map<String, dynamic> json) => CollectionCenterSummary(
        centerId: json['center_id'] as int?,
        centerName: json['center_name'] as String?,
        branchName: json['branch_name'] as String?,
        meetingDay: json['meeting_day'] as String?,
        meetingTime: json['meeting_time'] as String?,
        clientCount: json['client_count'] as int? ?? 0,
        target: (json['target'] as num?)?.toDouble() ?? 0,
        received: (json['received'] as num?)?.toDouble() ?? 0,
        balance: (json['balance'] as num?)?.toDouble() ?? 0,
        percent: (json['percent'] as num?)?.toDouble() ?? 0,
      );
}

class ArrearCenterSummary {
  final int? centerId;
  final String? centerName;
  final String? branchName;
  final String? meetingDay;
  final String? meetingTime;
  final int clientCount;
  final double arrearAmount;
  final int maxDaysOverdue;

  ArrearCenterSummary({
    required this.centerId,
    required this.centerName,
    required this.branchName,
    required this.meetingDay,
    required this.meetingTime,
    required this.clientCount,
    required this.arrearAmount,
    required this.maxDaysOverdue,
  });

  factory ArrearCenterSummary.fromJson(Map<String, dynamic> json) => ArrearCenterSummary(
        centerId: json['center_id'] as int?,
        centerName: json['center_name'] as String?,
        branchName: json['branch_name'] as String?,
        meetingDay: json['meeting_day'] as String?,
        meetingTime: json['meeting_time'] as String?,
        clientCount: json['client_count'] as int? ?? 0,
        arrearAmount: (json['arrear_amount'] as num?)?.toDouble() ?? 0,
        maxDaysOverdue: json['max_days_overdue'] as int? ?? 0,
      );
}

class RecordPaymentResult {
  final int loanId;
  final int scheduleId;
  final int installmentNo;
  final double collectedAmount;
  final String status;

  RecordPaymentResult({
    required this.loanId,
    required this.scheduleId,
    required this.installmentNo,
    required this.collectedAmount,
    required this.status,
  });

  factory RecordPaymentResult.fromJson(Map<String, dynamic> json) => RecordPaymentResult(
        loanId: json['loan_id'] as int,
        scheduleId: json['schedule_id'] as int,
        installmentNo: json['installment_no'] as int,
        collectedAmount: (json['collected_amount'] as num?)?.toDouble() ?? 0,
        status: json['status'] as String? ?? 'Collected',
      );
}

class CollectionsSummary {
  final int clientCount;
  final double dueAmount;
  final double collectedAmount;
  final double balance;

  CollectionsSummary({
    required this.clientCount,
    required this.dueAmount,
    required this.collectedAmount,
    required this.balance,
  });

  factory CollectionsSummary.fromJson(Map<String, dynamic> json) => CollectionsSummary(
        clientCount: json['client_count'] as int? ?? 0,
        dueAmount: (json['due_amount'] as num?)?.toDouble() ?? 0,
        collectedAmount: (json['collected_amount'] as num?)?.toDouble() ?? 0,
        balance: (json['balance'] as num?)?.toDouble() ?? 0,
      );
}

/// Today's collection at a glance (api/v1/dashboard/today_summary.php) --
/// shown as a quick-stats chip on the dashboard header, scoped the same way
/// as the Today's Pending Collection report.
class TodayCollectionSummary {
  final double dueAmount;
  final double collectedAmount;
  final int dueCount;
  final int collectedCount;

  TodayCollectionSummary({
    required this.dueAmount,
    required this.collectedAmount,
    required this.dueCount,
    required this.collectedCount,
  });

  factory TodayCollectionSummary.fromJson(Map<String, dynamic> json) => TodayCollectionSummary(
        dueAmount: (json['due_amount'] as num?)?.toDouble() ?? 0,
        collectedAmount: (json['collected_amount'] as num?)?.toDouble() ?? 0,
        dueCount: json['due_count'] as int? ?? 0,
        collectedCount: json['collected_count'] as int? ?? 0,
      );
}

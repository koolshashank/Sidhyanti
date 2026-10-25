class AttendanceStatus {
  final bool checkedIn;
  final bool checkedOut;
  final DateTime? checkInAt;
  final DateTime? checkOutAt;

  AttendanceStatus({
    required this.checkedIn,
    required this.checkedOut,
    required this.checkInAt,
    required this.checkOutAt,
  });

  factory AttendanceStatus.fromJson(Map<String, dynamic> json) => AttendanceStatus(
        checkedIn: json['checked_in'] as bool? ?? false,
        checkedOut: json['checked_out'] as bool? ?? false,
        checkInAt: json['check_in_at'] != null ? DateTime.tryParse(json['check_in_at'] as String) : null,
        checkOutAt: json['check_out_at'] != null ? DateTime.tryParse(json['check_out_at'] as String) : null,
      );
}

class AttendanceHistoryItem {
  final DateTime? date;
  final DateTime? checkInAt;
  final DateTime? checkOutAt;

  AttendanceHistoryItem({required this.date, required this.checkInAt, required this.checkOutAt});

  factory AttendanceHistoryItem.fromJson(Map<String, dynamic> json) => AttendanceHistoryItem(
        date: json['date'] != null ? DateTime.tryParse(json['date'] as String) : null,
        checkInAt: json['check_in_at'] != null ? DateTime.tryParse(json['check_in_at'] as String) : null,
        checkOutAt: json['check_out_at'] != null ? DateTime.tryParse(json['check_out_at'] as String) : null,
      );
}

class LeaveRequestItem {
  final int leaveRequestId;
  final String leaveType;
  final DateTime? fromDate;
  final DateTime? toDate;
  final String? reason;
  final String status; // Pending | Approved | Rejected
  final String? remarks;
  final String? approverName;
  final DateTime? createdAt;

  LeaveRequestItem({
    required this.leaveRequestId,
    required this.leaveType,
    required this.fromDate,
    required this.toDate,
    required this.reason,
    required this.status,
    required this.remarks,
    required this.approverName,
    required this.createdAt,
  });

  factory LeaveRequestItem.fromJson(Map<String, dynamic> json) => LeaveRequestItem(
        leaveRequestId: json['leave_request_id'] as int,
        leaveType: json['leave_type'] as String? ?? '',
        fromDate: json['from_date'] != null ? DateTime.tryParse(json['from_date'] as String) : null,
        toDate: json['to_date'] != null ? DateTime.tryParse(json['to_date'] as String) : null,
        reason: json['reason'] as String?,
        status: json['status'] as String? ?? 'Pending',
        remarks: json['remarks'] as String?,
        approverName: json['approver_name'] as String?,
        createdAt: json['created_at'] != null ? DateTime.tryParse(json['created_at'] as String) : null,
      );
}

class SalaryAdvanceRequestItem {
  final int requestId;
  final String requestType; // Personal | Emergency
  final double amount;
  final String? reason;
  final String status; // Pending | Approved | Rejected
  final String? remarks;
  final String? reviewerName;
  final DateTime? createdAt;

  SalaryAdvanceRequestItem({
    required this.requestId,
    required this.requestType,
    required this.amount,
    required this.reason,
    required this.status,
    required this.remarks,
    required this.reviewerName,
    required this.createdAt,
  });

  factory SalaryAdvanceRequestItem.fromJson(Map<String, dynamic> json) => SalaryAdvanceRequestItem(
        requestId: json['request_id'] as int,
        requestType: json['request_type'] as String? ?? '',
        amount: (json['amount'] as num?)?.toDouble() ?? 0,
        reason: json['reason'] as String?,
        status: json['status'] as String? ?? 'Pending',
        remarks: json['remarks'] as String?,
        reviewerName: json['reviewer_name'] as String?,
        createdAt: json['created_at'] != null ? DateTime.tryParse(json['created_at'] as String) : null,
      );
}

class MyPerformance {
  final int leadsSourcedThisMonth;
  final int loansDisbursedThisMonth;
  final double amountDisbursedThisMonth;
  final double collectedThisMonth;
  final int arrearLoanCount;
  final double arrearAmount;
  final int activeLoanClients;
  final double posTillDate;
  final double disbursementTarget;
  final double collectionDueThisMonth;
  final double estimatedIncentive;

  MyPerformance({
    required this.leadsSourcedThisMonth,
    required this.loansDisbursedThisMonth,
    required this.amountDisbursedThisMonth,
    required this.collectedThisMonth,
    required this.arrearLoanCount,
    required this.arrearAmount,
    required this.activeLoanClients,
    required this.posTillDate,
    required this.disbursementTarget,
    required this.collectionDueThisMonth,
    required this.estimatedIncentive,
  });

  factory MyPerformance.fromJson(Map<String, dynamic> json) => MyPerformance(
        leadsSourcedThisMonth: json['leads_sourced_this_month'] as int? ?? 0,
        loansDisbursedThisMonth: json['loans_disbursed_this_month'] as int? ?? 0,
        amountDisbursedThisMonth: (json['amount_disbursed_this_month'] as num?)?.toDouble() ?? 0,
        collectedThisMonth: (json['collected_this_month'] as num?)?.toDouble() ?? 0,
        arrearLoanCount: json['arrear_loan_count'] as int? ?? 0,
        arrearAmount: (json['arrear_amount'] as num?)?.toDouble() ?? 0,
        activeLoanClients: json['active_loan_clients'] as int? ?? 0,
        posTillDate: (json['pos_till_date'] as num?)?.toDouble() ?? 0,
        disbursementTarget: (json['disbursement_target'] as num?)?.toDouble() ?? 0,
        collectionDueThisMonth: (json['collection_due_this_month'] as num?)?.toDouble() ?? 0,
        estimatedIncentive: (json['estimated_incentive'] as num?)?.toDouble() ?? 0,
      );
}

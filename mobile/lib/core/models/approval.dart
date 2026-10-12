import 'loan.dart';

/// A pending leave request in a reviewer's (Branch Manager's) queue --
/// distinct from [LeaveRequestItem] (the submitter's own read-only view)
/// because it also carries who it's from.
class PendingLeaveRequest {
  final int leaveRequestId;
  final int staffId;
  final String staffName;
  final String leaveType;
  final DateTime? fromDate;
  final DateTime? toDate;
  final String? reason;
  final DateTime? createdAt;

  PendingLeaveRequest({
    required this.leaveRequestId,
    required this.staffId,
    required this.staffName,
    required this.leaveType,
    required this.fromDate,
    required this.toDate,
    required this.reason,
    required this.createdAt,
  });

  factory PendingLeaveRequest.fromJson(Map<String, dynamic> json) => PendingLeaveRequest(
        leaveRequestId: json['leave_request_id'] as int,
        staffId: json['staff_id'] as int,
        staffName: json['staff_name'] as String? ?? '',
        leaveType: json['leave_type'] as String? ?? '',
        fromDate: json['from_date'] != null ? DateTime.tryParse(json['from_date'] as String) : null,
        toDate: json['to_date'] != null ? DateTime.tryParse(json['to_date'] as String) : null,
        reason: json['reason'] as String?,
        createdAt: json['created_at'] != null ? DateTime.tryParse(json['created_at'] as String) : null,
      );
}

/// A pending Loan_Approval row -- either a loan-proposal sign-off or a
/// preclose request, depending on which `pendingApprovals(type: ...)` call
/// fetched it.
class LoanApprovalItem {
  final int loanApprovalId;
  final int loanId;
  final String loanNo;
  final String memberName;
  final double loanAmount;
  final String? requestRemarks;
  final String? requestedByName;
  final DateTime? createdAt;
  final PrecloseQuote? precloseQuote;

  LoanApprovalItem({
    required this.loanApprovalId,
    required this.loanId,
    required this.loanNo,
    required this.memberName,
    required this.loanAmount,
    required this.requestRemarks,
    required this.requestedByName,
    required this.createdAt,
    required this.precloseQuote,
  });

  factory LoanApprovalItem.fromJson(Map<String, dynamic> json) => LoanApprovalItem(
        loanApprovalId: json['loan_approval_id'] as int,
        loanId: json['loan_id'] as int,
        loanNo: json['loan_no'] as String? ?? '',
        memberName: json['member_name'] as String? ?? '',
        loanAmount: (json['loan_amount'] as num?)?.toDouble() ?? 0,
        requestRemarks: json['request_remarks'] as String?,
        requestedByName: json['requested_by_name'] as String?,
        createdAt: json['created_at'] != null ? DateTime.tryParse(json['created_at'] as String) : null,
        precloseQuote: json['preclose_quote'] != null ? PrecloseQuote.fromJson(json['preclose_quote'] as Map<String, dynamic>) : null,
      );
}

/// A pending partial-payment approval -- a short EMI payment awaiting
/// Branch Manager sign-off before it's finalized into the ledger.
class PartialPaymentApprovalItem {
  final int partialPaymentApprovalId;
  final int loanId;
  final String loanNo;
  final String memberName;
  final int installmentNo;
  final double emiAmount;
  final double requestedAmount;
  final DateTime? collectionDate;
  final String? remarks;
  final String? requestedByName;
  final DateTime? createdAt;
  final double arrearAmount;
  final int overdueInstallmentCount;
  final int? firstOverdueInstallmentNo;

  PartialPaymentApprovalItem({
    required this.partialPaymentApprovalId,
    required this.loanId,
    required this.loanNo,
    required this.memberName,
    required this.installmentNo,
    required this.emiAmount,
    required this.requestedAmount,
    required this.collectionDate,
    required this.remarks,
    required this.requestedByName,
    required this.createdAt,
    required this.arrearAmount,
    required this.overdueInstallmentCount,
    required this.firstOverdueInstallmentNo,
  });

  factory PartialPaymentApprovalItem.fromJson(Map<String, dynamic> json) {
    final arrear = json['arrear_summary'] as Map<String, dynamic>?;
    return PartialPaymentApprovalItem(
      partialPaymentApprovalId: json['partial_payment_approval_id'] as int,
      loanId: json['loan_id'] as int,
      loanNo: json['loan_no'] as String? ?? '',
      memberName: json['member_name'] as String? ?? '',
      installmentNo: json['installment_no'] as int? ?? 0,
      emiAmount: (json['emi_amount'] as num?)?.toDouble() ?? 0,
      requestedAmount: (json['requested_amount'] as num?)?.toDouble() ?? 0,
      collectionDate: json['collection_date'] != null ? DateTime.tryParse(json['collection_date'] as String) : null,
      remarks: json['remarks'] as String?,
      requestedByName: json['requested_by_name'] as String?,
      createdAt: json['created_at'] != null ? DateTime.tryParse(json['created_at'] as String) : null,
      arrearAmount: (arrear?['arrear_amount'] as num?)?.toDouble() ?? 0,
      overdueInstallmentCount: arrear?['overdue_installment_count'] as int? ?? 0,
      firstOverdueInstallmentNo: arrear?['first_overdue_installment_no'] as int?,
    );
  }
}

/// A center awaiting Branch Manager approval.
class PendingCenterItem {
  final int centerId;
  final String centerCode;
  final String centerName;
  final String? address;
  final String? centerPlace;
  final String? meetingDay;
  final String? meetingTime;
  final String? contactPerson;
  final String? contactNo;
  final String? branchName;
  final String? managedByName;
  final DateTime? createdAt;

  PendingCenterItem({
    required this.centerId,
    required this.centerCode,
    required this.centerName,
    required this.address,
    required this.centerPlace,
    required this.meetingDay,
    required this.meetingTime,
    required this.contactPerson,
    required this.contactNo,
    required this.branchName,
    required this.managedByName,
    required this.createdAt,
  });

  factory PendingCenterItem.fromJson(Map<String, dynamic> json) => PendingCenterItem(
        centerId: json['center_id'] as int,
        centerCode: json['center_code'] as String? ?? '',
        centerName: json['center_name'] as String? ?? '',
        address: json['address'] as String?,
        centerPlace: json['center_place'] as String?,
        meetingDay: json['meeting_day'] as String?,
        meetingTime: json['meeting_time'] as String?,
        contactPerson: json['contact_person'] as String?,
        contactNo: json['contact_no'] as String?,
        branchName: json['branch_name'] as String?,
        managedByName: json['managed_by_name'] as String?,
        createdAt: json['created_at'] != null ? DateTime.tryParse(json['created_at'] as String) : null,
      );
}

/// A loan at the Approved stage, awaiting disbursement.
class PendingDisbursementItem {
  final int loanId;
  final String loanNo;
  final String memberName;
  final String? centerName;
  final String? officerName;
  final double loanAmount;
  final DateTime? approvalDate;
  final bool photoCaptured;
  final String? photoUrl;
  final double processingFee;
  final double insuranceAmount;
  final double otherCharges;
  final double totalDeductions;
  final double netDisbursementAmount;
  final String? bankAccountMasked;

  PendingDisbursementItem({
    required this.loanId,
    required this.loanNo,
    required this.memberName,
    required this.centerName,
    required this.officerName,
    required this.loanAmount,
    required this.approvalDate,
    required this.photoCaptured,
    required this.photoUrl,
    required this.processingFee,
    required this.insuranceAmount,
    required this.otherCharges,
    required this.totalDeductions,
    required this.netDisbursementAmount,
    required this.bankAccountMasked,
  });

  factory PendingDisbursementItem.fromJson(Map<String, dynamic> json) => PendingDisbursementItem(
        loanId: json['loan_id'] as int,
        loanNo: json['loan_no'] as String? ?? '',
        memberName: json['member_name'] as String? ?? '',
        centerName: json['center_name'] as String?,
        officerName: json['officer_name'] as String?,
        loanAmount: (json['loan_amount'] as num?)?.toDouble() ?? 0,
        approvalDate: json['approval_date'] != null ? DateTime.tryParse(json['approval_date'] as String) : null,
        photoCaptured: json['photo_captured'] as bool? ?? false,
        photoUrl: json['photo_url'] as String?,
        processingFee: (json['processing_fee'] as num?)?.toDouble() ?? 0,
        insuranceAmount: (json['insurance_amount'] as num?)?.toDouble() ?? 0,
        otherCharges: (json['other_charges'] as num?)?.toDouble() ?? 0,
        totalDeductions: (json['total_deductions'] as num?)?.toDouble() ?? 0,
        netDisbursementAmount: (json['net_disbursement_amount'] as num?)?.toDouble() ?? 0,
        bankAccountMasked: json['bank_account_masked'] as String?,
      );
}

/// Daily Collection Report -- a single date's per-center breakdown.
class DcrCenterSummary {
  final int? centerId;
  final String centerName;
  final int dueCount;
  final double dueAmount;
  final double collectedAmount;
  final int collectedCount;
  final int pendingCount;
  final int partialCount;

  DcrCenterSummary({
    required this.centerId,
    required this.centerName,
    required this.dueCount,
    required this.dueAmount,
    required this.collectedAmount,
    required this.collectedCount,
    required this.pendingCount,
    required this.partialCount,
  });

  factory DcrCenterSummary.fromJson(Map<String, dynamic> json) => DcrCenterSummary(
        centerId: json['center_id'] as int?,
        centerName: json['center_name'] as String? ?? 'Unassigned',
        dueCount: json['due_count'] as int? ?? 0,
        dueAmount: (json['due_amount'] as num?)?.toDouble() ?? 0,
        collectedAmount: (json['collected_amount'] as num?)?.toDouble() ?? 0,
        collectedCount: json['collected_count'] as int? ?? 0,
        pendingCount: json['pending_count'] as int? ?? 0,
        partialCount: json['partial_count'] as int? ?? 0,
      );
}

class DcrReport {
  final DateTime date;
  final int dueCount;
  final double dueAmount;
  final double collectedAmount;
  final int collectedCount;
  final int pendingCount;
  final int partialCount;
  final List<DcrCenterSummary> byCenter;

  DcrReport({
    required this.date,
    required this.dueCount,
    required this.dueAmount,
    required this.collectedAmount,
    required this.collectedCount,
    required this.pendingCount,
    required this.partialCount,
    required this.byCenter,
  });

  factory DcrReport.fromJson(Map<String, dynamic> json) {
    final summary = json['summary'] as Map<String, dynamic>? ?? {};
    return DcrReport(
      date: DateTime.tryParse(json['date'] as String? ?? '') ?? DateTime.now(),
      dueCount: summary['due_count'] as int? ?? 0,
      dueAmount: (summary['due_amount'] as num?)?.toDouble() ?? 0,
      collectedAmount: (summary['collected_amount'] as num?)?.toDouble() ?? 0,
      collectedCount: summary['collected_count'] as int? ?? 0,
      pendingCount: summary['pending_count'] as int? ?? 0,
      partialCount: summary['partial_count'] as int? ?? 0,
      byCenter: (json['by_center'] as List<dynamic>? ?? [])
          .map((e) => DcrCenterSummary.fromJson(e as Map<String, dynamic>))
          .toList(),
    );
  }
}

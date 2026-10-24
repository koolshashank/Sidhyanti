class LoanMemberRef {
  final int memberId;
  final String fullName;
  final String? mobileNo;
  final String? enrollmentNo;

  LoanMemberRef({
    required this.memberId,
    required this.fullName,
    required this.mobileNo,
    required this.enrollmentNo,
  });

  factory LoanMemberRef.fromJson(Map<String, dynamic> json) => LoanMemberRef(
        memberId: json['member_id'] as int,
        fullName: json['full_name'] as String? ?? '',
        mobileNo: json['mobile_no'] as String?,
        enrollmentNo: json['enrollment_no'] as String?,
      );
}

class RepaymentInstallment {
  final int scheduleId;
  final int installmentNo;
  final DateTime? dueDate;
  final double emiAmount;
  final double principalDue;
  final double interestDue;
  final bool isPaid;
  final DateTime? collectionDate;
  final String status; // Paid | Overdue | Pending

  RepaymentInstallment({
    required this.scheduleId,
    required this.installmentNo,
    required this.dueDate,
    required this.emiAmount,
    required this.principalDue,
    required this.interestDue,
    required this.isPaid,
    required this.collectionDate,
    required this.status,
  });

  factory RepaymentInstallment.fromJson(Map<String, dynamic> json) => RepaymentInstallment(
        scheduleId: json['schedule_id'] as int,
        installmentNo: json['installment_no'] as int,
        dueDate: json['due_date'] != null ? DateTime.tryParse(json['due_date'] as String) : null,
        emiAmount: (json['emi_amount'] as num?)?.toDouble() ?? 0,
        principalDue: (json['principal_due'] as num?)?.toDouble() ?? 0,
        interestDue: (json['interest_due'] as num?)?.toDouble() ?? 0,
        isPaid: json['is_paid'] as bool? ?? false,
        collectionDate: json['collection_date'] != null ? DateTime.tryParse(json['collection_date'] as String) : null,
        status: json['status'] as String? ?? 'Pending',
      );
}

class OverdueSummary {
  final int count;
  final double amount;
  final int maxDaysOverdue;

  OverdueSummary({required this.count, required this.amount, required this.maxDaysOverdue});

  factory OverdueSummary.fromJson(Map<String, dynamic> json) => OverdueSummary(
        count: json['count'] as int? ?? 0,
        amount: (json['amount'] as num?)?.toDouble() ?? 0,
        maxDaysOverdue: json['max_days_overdue'] as int? ?? 0,
      );
}

/// What the member would pay (and save) if this loan were preclosed today.
class PrecloseQuote {
  final int installmentsPaid;
  final int totalInstallments;
  final double principalOutstanding;
  final double interestSavings;
  final double precloseAmount;

  PrecloseQuote({
    required this.installmentsPaid,
    required this.totalInstallments,
    required this.principalOutstanding,
    required this.interestSavings,
    required this.precloseAmount,
  });

  factory PrecloseQuote.fromJson(Map<String, dynamic> json) => PrecloseQuote(
        installmentsPaid: json['installments_paid'] as int? ?? 0,
        totalInstallments: json['total_installments'] as int? ?? 0,
        principalOutstanding: (json['principal_outstanding'] as num?)?.toDouble() ?? 0,
        interestSavings: (json['interest_savings'] as num?)?.toDouble() ?? 0,
        precloseAmount: (json['preclose_amount'] as num?)?.toDouble() ?? 0,
      );
}

/// One unpaid, past-due installment -- used by the "Collect Arrear" sheet
/// to cascade a single entered amount across installments oldest-first.
class OverdueInstallment {
  final int scheduleId;
  final int installmentNo;
  final DateTime? dueDate;
  final double emiAmount;

  OverdueInstallment({
    required this.scheduleId,
    required this.installmentNo,
    required this.dueDate,
    required this.emiAmount,
  });

  factory OverdueInstallment.fromJson(Map<String, dynamic> json) => OverdueInstallment(
        scheduleId: json['schedule_id'] as int,
        installmentNo: json['installment_no'] as int,
        dueDate: json['due_date'] != null ? DateTime.tryParse(json['due_date'] as String) : null,
        emiAmount: (json['emi_amount'] as num?)?.toDouble() ?? 0,
      );
}

class LoanDetail {
  final int loanId;
  final String loanNo;
  final int? loanCycle;
  final int? schemeId;
  final bool canChangeScheme;
  final bool canConductGrt;
  final bool canRequestPreclose;
  final bool canViewKfs;
  final PrecloseQuote? precloseQuote;
  final LoanMemberRef member;
  final String? branchName;
  final String? centerName;
  final String? purposeName;
  final String? stage;
  final String? officerName;
  final double loanAmount;
  final int tenureMonths;
  final double interestRatePercent;
  final double emiAmount;
  final DateTime? applicationDate;
  final DateTime? disbursementDate;
  final int paidCount;
  final int totalInstallments;
  final List<RepaymentInstallment> repaymentSchedule;
  final OverdueSummary overdue;

  LoanDetail({
    required this.loanId,
    required this.loanNo,
    required this.loanCycle,
    required this.schemeId,
    required this.canChangeScheme,
    required this.canConductGrt,
    required this.canRequestPreclose,
    required this.canViewKfs,
    required this.precloseQuote,
    required this.member,
    required this.branchName,
    required this.centerName,
    required this.purposeName,
    required this.stage,
    required this.officerName,
    required this.loanAmount,
    required this.tenureMonths,
    required this.interestRatePercent,
    required this.emiAmount,
    required this.applicationDate,
    required this.disbursementDate,
    required this.paidCount,
    required this.totalInstallments,
    required this.repaymentSchedule,
    required this.overdue,
  });

  factory LoanDetail.fromJson(Map<String, dynamic> json) => LoanDetail(
        loanId: json['loan_id'] as int,
        loanNo: json['loan_no'] as String? ?? '',
        loanCycle: json['loan_cycle'] as int?,
        schemeId: json['scheme_id'] as int?,
        canChangeScheme: json['can_change_scheme'] as bool? ?? false,
        canConductGrt: json['can_conduct_grt'] as bool? ?? false,
        canRequestPreclose: json['can_request_preclose'] as bool? ?? false,
        canViewKfs: json['can_view_kfs'] as bool? ?? false,
        precloseQuote: json['preclose_quote'] != null ? PrecloseQuote.fromJson(json['preclose_quote'] as Map<String, dynamic>) : null,
        member: LoanMemberRef.fromJson(json['member'] as Map<String, dynamic>),
        branchName: json['branch_name'] as String?,
        centerName: json['center_name'] as String?,
        purposeName: json['purpose_name'] as String?,
        stage: json['stage'] as String?,
        officerName: json['officer_name'] as String?,
        loanAmount: (json['loan_amount'] as num?)?.toDouble() ?? 0,
        tenureMonths: json['tenure_months'] as int? ?? 0,
        interestRatePercent: (json['interest_rate_percent'] as num?)?.toDouble() ?? 0,
        emiAmount: (json['emi_amount'] as num?)?.toDouble() ?? 0,
        applicationDate: json['application_date'] != null ? DateTime.tryParse(json['application_date'] as String) : null,
        disbursementDate: json['disbursement_date'] != null ? DateTime.tryParse(json['disbursement_date'] as String) : null,
        paidCount: json['paid_count'] as int? ?? 0,
        totalInstallments: json['total_installments'] as int? ?? 0,
        repaymentSchedule: (json['repayment_schedule'] as List<dynamic>? ?? [])
            .map((e) => RepaymentInstallment.fromJson(e as Map<String, dynamic>))
            .toList(),
        overdue: OverdueSummary.fromJson(json['overdue'] as Map<String, dynamic>? ?? {}),
      );
}

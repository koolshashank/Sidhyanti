class CenterCollectionRow {
  final String centerName;
  final String? branchName;
  final int clientCount;
  final double dueAmount;
  final double collectedAmount;
  final double balance;

  CenterCollectionRow({
    required this.centerName,
    required this.branchName,
    required this.clientCount,
    required this.dueAmount,
    required this.collectedAmount,
    required this.balance,
  });

  factory CenterCollectionRow.fromJson(Map<String, dynamic> json) => CenterCollectionRow(
        centerName: json['center_name'] as String? ?? '-',
        branchName: json['branch_name'] as String?,
        clientCount: json['client_count'] as int? ?? 0,
        dueAmount: (json['due_amount'] as num?)?.toDouble() ?? 0,
        collectedAmount: (json['collected_amount'] as num?)?.toDouble() ?? 0,
        balance: (json['balance'] as num?)?.toDouble() ?? 0,
      );
}

class TodayPendingRow {
  final int loanId;
  final String loanNo;
  final String borrowerName;
  final String? coBorrowerName;
  final String? branchName;
  final String? centerName;
  final String? officerName;
  final double emiAmount;

  TodayPendingRow({
    required this.loanId,
    required this.loanNo,
    required this.borrowerName,
    required this.coBorrowerName,
    required this.branchName,
    required this.centerName,
    required this.officerName,
    required this.emiAmount,
  });

  factory TodayPendingRow.fromJson(Map<String, dynamic> json) => TodayPendingRow(
        loanId: json['loan_id'] as int,
        loanNo: json['loan_no'] as String? ?? '',
        borrowerName: json['borrower_name'] as String? ?? '',
        coBorrowerName: json['co_borrower_name'] as String?,
        branchName: json['branch_name'] as String?,
        centerName: json['center_name'] as String?,
        officerName: json['officer_name'] as String?,
        emiAmount: (json['emi_amount'] as num?)?.toDouble() ?? 0,
      );
}

class TodayDisbursedRow {
  final int loanId;
  final String loanNo;
  final String memberName;
  final String? branchName;
  final String? centerName;
  final String? officerName;
  final double amount;
  final String? disbursementMode;

  TodayDisbursedRow({
    required this.loanId,
    required this.loanNo,
    required this.memberName,
    required this.branchName,
    required this.centerName,
    required this.officerName,
    required this.amount,
    required this.disbursementMode,
  });

  factory TodayDisbursedRow.fromJson(Map<String, dynamic> json) => TodayDisbursedRow(
        loanId: json['loan_id'] as int,
        loanNo: json['loan_no'] as String? ?? '',
        memberName: json['member_name'] as String? ?? '',
        branchName: json['branch_name'] as String?,
        centerName: json['center_name'] as String?,
        officerName: json['officer_name'] as String?,
        amount: (json['amount'] as num?)?.toDouble() ?? 0,
        disbursementMode: json['disbursement_mode'] as String?,
      );
}

class DueDisbursementRow {
  final int loanId;
  final String loanNo;
  final String memberName;
  final String? branchName;
  final String? centerName;
  final String? officerName;
  final double amount;
  final DateTime? approvalDate;

  DueDisbursementRow({
    required this.loanId,
    required this.loanNo,
    required this.memberName,
    required this.branchName,
    required this.centerName,
    required this.officerName,
    required this.amount,
    required this.approvalDate,
  });

  factory DueDisbursementRow.fromJson(Map<String, dynamic> json) => DueDisbursementRow(
        loanId: json['loan_id'] as int,
        loanNo: json['loan_no'] as String? ?? '',
        memberName: json['member_name'] as String? ?? '',
        branchName: json['branch_name'] as String?,
        centerName: json['center_name'] as String?,
        officerName: json['officer_name'] as String?,
        amount: (json['amount'] as num?)?.toDouble() ?? 0,
        approvalDate: json['approval_date'] != null ? DateTime.tryParse(json['approval_date'] as String) : null,
      );
}

class ArrearsClientRow {
  final int loanId;
  final String loanNo;
  final String memberName;
  final String? mobileNo;
  final String? branchName;
  final String? centerName;
  final String? officerName;
  final int firstBrokenInstallment;
  final int brokenCount;
  final double brokenAmount;
  final int daysOverdue;

  ArrearsClientRow({
    required this.loanId,
    required this.loanNo,
    required this.memberName,
    required this.mobileNo,
    required this.branchName,
    required this.centerName,
    required this.officerName,
    required this.firstBrokenInstallment,
    required this.brokenCount,
    required this.brokenAmount,
    required this.daysOverdue,
  });

  factory ArrearsClientRow.fromJson(Map<String, dynamic> json) => ArrearsClientRow(
        loanId: json['loan_id'] as int,
        loanNo: json['loan_no'] as String? ?? '',
        memberName: json['member_name'] as String? ?? '',
        mobileNo: json['mobile_no'] as String?,
        branchName: json['branch_name'] as String?,
        centerName: json['center_name'] as String?,
        officerName: json['officer_name'] as String?,
        firstBrokenInstallment: json['first_broken_installment'] as int? ?? 0,
        brokenCount: json['broken_count'] as int? ?? 0,
        brokenAmount: (json['broken_amount'] as num?)?.toDouble() ?? 0,
        daysOverdue: json['days_overdue'] as int? ?? 0,
      );
}

class DefaultLoanRow {
  final int loanId;
  final String loanNo;
  final String memberName;
  final String? mobileNo;
  final String? branchName;
  final String? centerName;
  final String? officerName;
  final DateTime? lastDueDate;
  final double outstanding;
  final int daysPastTenure;

  DefaultLoanRow({
    required this.loanId,
    required this.loanNo,
    required this.memberName,
    required this.mobileNo,
    required this.branchName,
    required this.centerName,
    required this.officerName,
    required this.lastDueDate,
    required this.outstanding,
    required this.daysPastTenure,
  });

  factory DefaultLoanRow.fromJson(Map<String, dynamic> json) => DefaultLoanRow(
        loanId: json['loan_id'] as int,
        loanNo: json['loan_no'] as String? ?? '',
        memberName: json['member_name'] as String? ?? '',
        mobileNo: json['mobile_no'] as String?,
        branchName: json['branch_name'] as String?,
        centerName: json['center_name'] as String?,
        officerName: json['officer_name'] as String?,
        lastDueDate: json['last_due_date'] != null ? DateTime.tryParse(json['last_due_date'] as String) : null,
        outstanding: (json['outstanding'] as num?)?.toDouble() ?? 0,
        daysPastTenure: json['days_past_tenure'] as int? ?? 0,
      );
}

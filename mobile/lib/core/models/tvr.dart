/// A loan still awaiting its Tele-Verification call
/// (api/v1/loans/tvr_pending.php).
class TvrPendingItem {
  final int loanId;
  final String loanNo;
  final String memberName;
  final String? centerName;
  final double loanAmount;
  final String? stage;
  final DateTime? applicationDate;

  TvrPendingItem({
    required this.loanId,
    required this.loanNo,
    required this.memberName,
    required this.centerName,
    required this.loanAmount,
    required this.stage,
    required this.applicationDate,
  });

  factory TvrPendingItem.fromJson(Map<String, dynamic> json) => TvrPendingItem(
        loanId: json['loan_id'] as int,
        loanNo: json['loan_no'] as String? ?? '',
        memberName: json['member_name'] as String? ?? '',
        centerName: json['center_name'] as String?,
        loanAmount: (json['loan_amount'] as num?)?.toDouble() ?? 0,
        stage: json['stage'] as String?,
        applicationDate: json['application_date'] != null ? DateTime.tryParse(json['application_date'] as String) : null,
      );
}

/// One TVR_Status_Master row (Pending / Approved / Rejected / Code 2).
class TvrStatusOption {
  final int id;
  final String name;

  TvrStatusOption({required this.id, required this.name});

  factory TvrStatusOption.fromJson(Map<String, dynamic> json) =>
      TvrStatusOption(id: json['id'] as int, name: json['name'] as String? ?? '');
}

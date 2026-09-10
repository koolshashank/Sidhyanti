/// One member on the "Reloan" worklist -- eligible for a new loan because
/// their previous loan is Closed/Preclosed and a Reloan_Eligibility_Rule
/// matches (see api/includes/reloan_helper.php on the backend).
class ReloanCandidate {
  final int memberId;
  final String fullName;
  final String? mobileNo;
  final String? enrollmentNo;
  final String previousLoanNo;
  final double previousLoanAmount;
  final int cycleNumber;
  final int latePayments;
  final String ruleName;
  final double increasePercent;
  final double eligibleAmount;

  ReloanCandidate({
    required this.memberId,
    required this.fullName,
    required this.mobileNo,
    required this.enrollmentNo,
    required this.previousLoanNo,
    required this.previousLoanAmount,
    required this.cycleNumber,
    required this.latePayments,
    required this.ruleName,
    required this.increasePercent,
    required this.eligibleAmount,
  });

  factory ReloanCandidate.fromJson(Map<String, dynamic> json) => ReloanCandidate(
        memberId: json['member_id'] as int,
        fullName: json['full_name'] as String? ?? '',
        mobileNo: json['mobile_no'] as String?,
        enrollmentNo: json['enrollment_no'] as String?,
        previousLoanNo: json['previous_loan_no'] as String? ?? '',
        previousLoanAmount: (json['previous_loan_amount'] as num?)?.toDouble() ?? 0,
        cycleNumber: json['cycle_number'] as int? ?? 0,
        latePayments: json['late_payments'] as int? ?? 0,
        ruleName: json['rule_name'] as String? ?? '',
        increasePercent: (json['increase_percent'] as num?)?.toDouble() ?? 0,
        eligibleAmount: (json['eligible_amount'] as num?)?.toDouble() ?? 0,
      );
}

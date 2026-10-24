/// The auto-computed figures prefilled onto the Day Closing Report form --
/// see api/v1/reports/dcr_closing_prefill.php. Every one of these is
/// recomputed again server-side at submit time, so this is display-only.
class DayClosingPrefill {
  final String date;
  final bool alreadySubmitted;
  final double loanDisbursement;
  final double openingBalance;
  final double collectionDues;
  final double collectedAmount;
  final double preclosureAmount;
  final double arrearRecoveryAmount;

  DayClosingPrefill({
    required this.date,
    required this.alreadySubmitted,
    required this.loanDisbursement,
    required this.openingBalance,
    required this.collectionDues,
    required this.collectedAmount,
    required this.preclosureAmount,
    required this.arrearRecoveryAmount,
  });

  factory DayClosingPrefill.fromJson(Map<String, dynamic> json) => DayClosingPrefill(
        date: json['date'] as String,
        alreadySubmitted: json['already_submitted'] as bool? ?? false,
        loanDisbursement: (json['loan_disbursement'] as num?)?.toDouble() ?? 0,
        openingBalance: (json['opening_balance'] as num?)?.toDouble() ?? 0,
        collectionDues: (json['collection_dues'] as num?)?.toDouble() ?? 0,
        collectedAmount: (json['collected_amount'] as num?)?.toDouble() ?? 0,
        preclosureAmount: (json['preclosure_amount'] as num?)?.toDouble() ?? 0,
        arrearRecoveryAmount: (json['arrear_recovery_amount'] as num?)?.toDouble() ?? 0,
      );
}

/// The server's recomputed totals after a successful submit -- see
/// api/v1/reports/dcr_closing_create.php.
class DayClosingResult {
  final int dayClosingId;
  final double totalOutflow;
  final double totalInflow;
  final double grandTotal;

  DayClosingResult({
    required this.dayClosingId,
    required this.totalOutflow,
    required this.totalInflow,
    required this.grandTotal,
  });

  factory DayClosingResult.fromJson(Map<String, dynamic> json) => DayClosingResult(
        dayClosingId: json['day_closing_id'] as int,
        totalOutflow: (json['total_outflow'] as num?)?.toDouble() ?? 0,
        totalInflow: (json['total_inflow'] as num?)?.toDouble() ?? 0,
        grandTotal: (json['grand_total'] as num?)?.toDouble() ?? 0,
      );
}

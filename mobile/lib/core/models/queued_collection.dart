/// A payment that couldn't reach the server (no network) and is waiting to
/// be retried -- see CollectionQueueStore. Carries everything
/// CollectionsApi.record() needs, plus display fields (borrowerName,
/// scheduleId) so the pending-sync UI can show something meaningful
/// without a fresh server round-trip.
class QueuedCollection {
  final int loanId;
  final int scheduleId;
  final int installmentNo;
  final double amount;
  final DateTime collectionDate;
  final String? remarks;
  final String borrowerName;
  final DateTime queuedAt;

  QueuedCollection({
    required this.loanId,
    required this.scheduleId,
    required this.installmentNo,
    required this.amount,
    required this.collectionDate,
    this.remarks,
    required this.borrowerName,
    required this.queuedAt,
  });

  Map<String, dynamic> toJson() => {
        'loan_id': loanId,
        'schedule_id': scheduleId,
        'installment_no': installmentNo,
        'amount': amount,
        'collection_date': collectionDate.toIso8601String(),
        if (remarks != null) 'remarks': remarks,
        'borrower_name': borrowerName,
        'queued_at': queuedAt.toIso8601String(),
      };

  factory QueuedCollection.fromJson(Map<String, dynamic> json) => QueuedCollection(
        loanId: json['loan_id'] as int,
        scheduleId: json['schedule_id'] as int,
        installmentNo: json['installment_no'] as int,
        amount: (json['amount'] as num).toDouble(),
        collectionDate: DateTime.parse(json['collection_date'] as String),
        remarks: json['remarks'] as String?,
        borrowerName: json['borrower_name'] as String? ?? '',
        queuedAt: DateTime.parse(json['queued_at'] as String),
      );
}

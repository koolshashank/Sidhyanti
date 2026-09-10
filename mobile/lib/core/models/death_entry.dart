class DeathEntryItem {
  final int deathEntryId;
  final DateTime? dateOfDeath;
  final String? cause;
  final String? informantName;
  final String? informantRelation;
  final String? proofUrl;
  final String status; // Pending | Approved | Rejected
  final String? remarks;
  final String? reviewerName;
  final DateTime? createdAt;

  DeathEntryItem({
    required this.deathEntryId,
    required this.dateOfDeath,
    required this.cause,
    required this.informantName,
    required this.informantRelation,
    required this.proofUrl,
    required this.status,
    required this.remarks,
    required this.reviewerName,
    required this.createdAt,
  });

  factory DeathEntryItem.fromJson(Map<String, dynamic> json) => DeathEntryItem(
        deathEntryId: json['death_entry_id'] as int,
        dateOfDeath: json['date_of_death'] != null ? DateTime.tryParse(json['date_of_death'] as String) : null,
        cause: json['cause'] as String?,
        informantName: json['informant_name'] as String?,
        informantRelation: json['informant_relation'] as String?,
        proofUrl: json['proof_url'] as String?,
        status: json['status'] as String? ?? 'Pending',
        remarks: json['remarks'] as String?,
        reviewerName: json['reviewer_name'] as String?,
        createdAt: json['created_at'] != null ? DateTime.tryParse(json['created_at'] as String) : null,
      );
}

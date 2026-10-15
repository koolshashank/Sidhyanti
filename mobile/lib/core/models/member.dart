class MemberSummary {
  final int memberId;
  final String enrollmentNo;
  final String fullName;
  final String? mobileNo;
  final String? branchName;
  final String? centerName;
  final String? stage;
  final String? assignedOfficer;
  final String? photoUrl;

  MemberSummary({
    required this.memberId,
    required this.enrollmentNo,
    required this.fullName,
    required this.mobileNo,
    required this.branchName,
    required this.centerName,
    required this.stage,
    required this.assignedOfficer,
    required this.photoUrl,
  });

  factory MemberSummary.fromJson(Map<String, dynamic> json) => MemberSummary(
        memberId: json['member_id'] as int,
        enrollmentNo: json['enrollment_no'] as String? ?? '',
        fullName: json['full_name'] as String? ?? '',
        mobileNo: json['mobile_no'] as String?,
        branchName: json['branch_name'] as String?,
        centerName: json['center_name'] as String?,
        stage: json['stage'] as String?,
        assignedOfficer: json['assigned_officer'] as String?,
        photoUrl: json['photo_url'] as String?,
      );
}

class MemberLoanSummary {
  final int loanId;
  final String loanNo;
  final double amount;
  final String? stage;
  final DateTime? applicationDate;
  final DateTime? disbursementDate;

  MemberLoanSummary({
    required this.loanId,
    required this.loanNo,
    required this.amount,
    required this.stage,
    required this.applicationDate,
    required this.disbursementDate,
  });

  factory MemberLoanSummary.fromJson(Map<String, dynamic> json) => MemberLoanSummary(
        loanId: json['loan_id'] as int,
        loanNo: json['loan_no'] as String? ?? '',
        amount: (json['amount'] as num?)?.toDouble() ?? 0,
        stage: json['stage'] as String?,
        applicationDate: json['application_date'] != null ? DateTime.tryParse(json['application_date'] as String) : null,
        disbursementDate: json['disbursement_date'] != null ? DateTime.tryParse(json['disbursement_date'] as String) : null,
      );
}

class ReloanEligibility {
  final String previousLoanNo;
  final double previousLoanAmount;
  final int cycleNumber;
  final int latePayments;
  final String ruleName;
  final double increasePercent;
  final double eligibleAmount;

  ReloanEligibility({
    required this.previousLoanNo,
    required this.previousLoanAmount,
    required this.cycleNumber,
    required this.latePayments,
    required this.ruleName,
    required this.increasePercent,
    required this.eligibleAmount,
  });

  factory ReloanEligibility.fromJson(Map<String, dynamic> json) => ReloanEligibility(
        previousLoanNo: json['previous_loan_no'] as String? ?? '',
        previousLoanAmount: (json['previous_loan_amount'] as num?)?.toDouble() ?? 0,
        cycleNumber: json['cycle_number'] as int? ?? 0,
        latePayments: json['late_payments'] as int? ?? 0,
        ruleName: json['rule_name'] as String? ?? '',
        increasePercent: (json['increase_percent'] as num?)?.toDouble() ?? 0,
        eligibleAmount: (json['eligible_amount'] as num?)?.toDouble() ?? 0,
      );
}

class MemberDetail {
  final int memberId;
  final String enrollmentNo;
  final String fullName;
  final String? fatherHusbandName;
  final String? gender;
  final DateTime? dob;
  final String? maritalStatus;
  final String? mobileNo;
  final String? address;
  final String? villageCity;
  final String? districtName;
  final String? stateName;
  final String? pincode;
  final String? branchName;
  final String? centerName;
  final String? currentStage;
  final String? assignedOfficer;
  final bool isActive;
  final DateTime? enrollmentDate;
  final double outstandingPrincipal;
  final String? occupationName;
  final String? aadharNo;
  final String? panNo;
  final String? leadSource;
  final String? photoUrl;
  final bool canAddLoan;
  final int nextLoanCycle;
  final ReloanEligibility? reloanEligibility;
  final List<MemberLoanSummary> loans;

  MemberDetail({
    required this.memberId,
    required this.enrollmentNo,
    required this.fullName,
    required this.fatherHusbandName,
    required this.gender,
    required this.dob,
    required this.maritalStatus,
    required this.mobileNo,
    required this.address,
    required this.villageCity,
    required this.districtName,
    required this.stateName,
    required this.pincode,
    required this.branchName,
    required this.centerName,
    required this.currentStage,
    required this.assignedOfficer,
    required this.isActive,
    required this.enrollmentDate,
    required this.outstandingPrincipal,
    required this.occupationName,
    required this.aadharNo,
    required this.panNo,
    required this.leadSource,
    required this.photoUrl,
    required this.canAddLoan,
    required this.nextLoanCycle,
    required this.reloanEligibility,
    required this.loans,
  });

  factory MemberDetail.fromJson(Map<String, dynamic> json) => MemberDetail(
        memberId: json['member_id'] as int,
        enrollmentNo: json['enrollment_no'] as String? ?? '',
        fullName: json['full_name'] as String? ?? '',
        fatherHusbandName: json['father_husband_name'] as String?,
        gender: json['gender'] as String?,
        dob: json['dob'] != null ? DateTime.tryParse(json['dob'] as String) : null,
        maritalStatus: json['marital_status'] as String?,
        mobileNo: json['mobile_no'] as String?,
        address: json['address'] as String?,
        villageCity: json['village_city'] as String?,
        districtName: json['district_name'] as String?,
        stateName: json['state_name'] as String?,
        pincode: json['pincode'] as String?,
        branchName: json['branch_name'] as String?,
        centerName: json['center_name'] as String?,
        currentStage: json['current_stage'] as String?,
        assignedOfficer: json['assigned_officer'] as String?,
        isActive: json['is_active'] as bool? ?? true,
        enrollmentDate: json['enrollment_date'] != null ? DateTime.tryParse(json['enrollment_date'] as String) : null,
        outstandingPrincipal: (json['outstanding_principal'] as num?)?.toDouble() ?? 0,
        occupationName: json['occupation_name'] as String?,
        aadharNo: json['aadhar_no'] as String?,
        panNo: json['pan_no'] as String?,
        leadSource: json['lead_source'] as String?,
        photoUrl: json['photo_url'] as String?,
        canAddLoan: json['can_add_loan'] as bool? ?? true,
        nextLoanCycle: json['next_loan_cycle'] as int? ?? 1,
        reloanEligibility: json['reloan_eligibility'] != null
            ? ReloanEligibility.fromJson(json['reloan_eligibility'] as Map<String, dynamic>)
            : null,
        loans: (json['loans'] as List<dynamic>? ?? [])
            .map((e) => MemberLoanSummary.fromJson(e as Map<String, dynamic>))
            .toList(),
      );
}

class MemberDocument {
  final int docId;
  final String docType;
  final String? docNumber;
  final String? fileUrl;
  final bool isVerified;
  final DateTime? uploadedAt;

  MemberDocument({
    required this.docId,
    required this.docType,
    required this.docNumber,
    required this.fileUrl,
    required this.isVerified,
    required this.uploadedAt,
  });

  factory MemberDocument.fromJson(Map<String, dynamic> json) => MemberDocument(
        docId: json['doc_id'] as int,
        docType: json['doc_type'] as String? ?? '',
        docNumber: json['doc_number'] as String?,
        fileUrl: json['file_url'] as String?,
        isVerified: json['is_verified'] as bool? ?? false,
        uploadedAt: json['uploaded_at'] != null ? DateTime.tryParse(json['uploaded_at'] as String) : null,
      );
}

class MemberRepaymentItem {
  final int loanId;
  final String loanNo;
  final int installmentNo;
  final DateTime? dueDate;
  final double emiAmount;
  final bool isPaid;
  final DateTime? collectionDate;
  final String status;

  MemberRepaymentItem({
    required this.loanId,
    required this.loanNo,
    required this.installmentNo,
    required this.dueDate,
    required this.emiAmount,
    required this.isPaid,
    required this.collectionDate,
    required this.status,
  });

  factory MemberRepaymentItem.fromJson(Map<String, dynamic> json) => MemberRepaymentItem(
        loanId: json['loan_id'] as int,
        loanNo: json['loan_no'] as String? ?? '',
        installmentNo: json['installment_no'] as int,
        dueDate: json['due_date'] != null ? DateTime.tryParse(json['due_date'] as String) : null,
        emiAmount: (json['emi_amount'] as num?)?.toDouble() ?? 0,
        isPaid: json['is_paid'] as bool? ?? false,
        collectionDate: json['collection_date'] != null ? DateTime.tryParse(json['collection_date'] as String) : null,
        status: json['status'] as String? ?? 'Pending',
      );
}

class IncompleteJourney {
  final int memberId;
  final String memberName;
  final int? centerId;
  final String? centerName;
  final int? coBorrowerId;
  final String? coBorrowerName;
  final String resumeStage; // 'coborrower' | 'kyc_member'

  IncompleteJourney({
    required this.memberId,
    required this.memberName,
    required this.centerId,
    required this.centerName,
    required this.coBorrowerId,
    required this.coBorrowerName,
    required this.resumeStage,
  });

  factory IncompleteJourney.fromJson(Map<String, dynamic> json) => IncompleteJourney(
        memberId: json['member_id'] as int,
        memberName: json['member_name'] as String? ?? '',
        centerId: json['center_id'] as int?,
        centerName: json['center_name'] as String?,
        coBorrowerId: json['coborrower_id'] as int?,
        coBorrowerName: json['coborrower_name'] as String?,
        resumeStage: json['resume_stage'] as String? ?? 'coborrower',
      );
}

/// One row of the "Customer Journey" timeline (api/v1/members/journey.php)
/// -- every touchpoint across the lead + all its loans: stage changes,
/// interactions, KYC uploads, loan applied/GRT/TVR/disbursement/payments,
/// and tasks. Mirrors member_view.php's web timeline exactly.
class JourneyEvent {
  final DateTime? eventDate;
  final String eventType;
  final String eventTitle;
  final String? eventDetail;
  final String? relatedLoanNo;
  final String? performedBy;
  final double? amount;

  JourneyEvent({
    required this.eventDate,
    required this.eventType,
    required this.eventTitle,
    required this.eventDetail,
    required this.relatedLoanNo,
    required this.performedBy,
    required this.amount,
  });

  factory JourneyEvent.fromJson(Map<String, dynamic> json) => JourneyEvent(
        eventDate: json['event_date'] != null ? DateTime.tryParse(json['event_date'] as String) : null,
        eventType: json['event_type'] as String? ?? '',
        eventTitle: json['event_title'] as String? ?? '',
        eventDetail: json['event_detail'] as String?,
        relatedLoanNo: json['related_loan_no'] as String?,
        performedBy: json['performed_by'] as String?,
        amount: (json['amount'] as num?)?.toDouble(),
      );
}

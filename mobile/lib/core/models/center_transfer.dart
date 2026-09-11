/// One row in the Area Manager's "pick a center to transfer" list
/// (api/v1/centers/branch_list.php) -- every active center in their branch,
/// not just ones they personally manage.
class CenterForTransfer {
  final int centerId;
  final String? centerCode;
  final String centerName;
  final int branchId;
  final int? managedById;
  final String? managedByName;

  CenterForTransfer({
    required this.centerId,
    required this.centerCode,
    required this.centerName,
    required this.branchId,
    required this.managedById,
    required this.managedByName,
  });

  factory CenterForTransfer.fromJson(Map<String, dynamic> json) => CenterForTransfer(
        centerId: json['center_id'] as int,
        centerCode: json['center_code'] as String?,
        centerName: json['center_name'] as String? ?? '',
        branchId: json['branch_id'] as int? ?? 0,
        managedById: json['managed_by_id'] as int?,
        managedByName: json['managed_by_name'] as String?,
      );
}

/// One row in the "new officer" picker for a Center Transfer
/// (api/v1/centers/branch_staff.php) -- active staff in the same branch.
class BranchStaffOption {
  final int staffId;
  final String fullName;

  BranchStaffOption({required this.staffId, required this.fullName});

  factory BranchStaffOption.fromJson(Map<String, dynamic> json) => BranchStaffOption(
        staffId: json['staff_id'] as int,
        fullName: json['full_name'] as String? ?? '',
      );
}

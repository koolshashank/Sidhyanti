class Staff {
  final int id;
  final String fullName;
  final String? employeeCode;
  final String? photo;
  final String role;
  final int? branchId;
  final String? branchName;
  final String? designation;
  final List<String> menuItems;

  Staff({
    required this.id,
    required this.fullName,
    this.employeeCode,
    this.photo,
    required this.role,
    required this.branchId,
    this.branchName,
    required this.designation,
    this.menuItems = const [],
  });

  factory Staff.fromJson(Map<String, dynamic> json) => Staff(
        id: json['id'] as int,
        fullName: json['full_name'] as String,
        employeeCode: json['employee_code'] as String?,
        photo: json['photo'] as String?,
        role: json['role'] as String? ?? '',
        branchId: json['branch_id'] as int?,
        branchName: json['branch_name'] as String?,
        designation: json['designation'] as String?,
        menuItems: (json['menu_items'] as List<dynamic>? ?? []).map((e) => e as String).toList(),
      );

  Map<String, dynamic> toJson() => {
        'id': id,
        'full_name': fullName,
        'employee_code': employeeCode,
        'photo': photo,
        'role': role,
        'branch_id': branchId,
        'branch_name': branchName,
        'designation': designation,
        'menu_items': menuItems,
      };
}

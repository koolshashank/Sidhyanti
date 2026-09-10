class Staff {
  final int id;
  final String fullName;
  final String role;
  final int? branchId;
  final String? designation;
  final List<String> menuItems;

  Staff({
    required this.id,
    required this.fullName,
    required this.role,
    required this.branchId,
    required this.designation,
    this.menuItems = const [],
  });

  factory Staff.fromJson(Map<String, dynamic> json) => Staff(
        id: json['id'] as int,
        fullName: json['full_name'] as String,
        role: json['role'] as String? ?? '',
        branchId: json['branch_id'] as int?,
        designation: json['designation'] as String?,
        menuItems: (json['menu_items'] as List<dynamic>? ?? []).map((e) => e as String).toList(),
      );

  Map<String, dynamic> toJson() => {
        'id': id,
        'full_name': fullName,
        'role': role,
        'branch_id': branchId,
        'designation': designation,
        'menu_items': menuItems,
      };
}

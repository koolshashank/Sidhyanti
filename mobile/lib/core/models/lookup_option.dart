class LookupOption {
  final int id;
  final String name;

  LookupOption({required this.id, required this.name});

  factory LookupOption.fromJson(Map<String, dynamic> json) =>
      LookupOption(id: json['id'] as int, name: json['name'] as String? ?? '');
}

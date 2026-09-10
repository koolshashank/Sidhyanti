class PaginatedResult<T> {
  final List<T> items;
  final int page;
  final int perPage;
  final int total;
  final int totalPages;

  PaginatedResult({
    required this.items,
    required this.page,
    required this.perPage,
    required this.total,
    required this.totalPages,
  });

  bool get hasMore => page < totalPages;

  factory PaginatedResult.fromJson(
    Map<String, dynamic> json,
    T Function(Map<String, dynamic>) itemFromJson,
  ) {
    final rawItems = json['items'] as List<dynamic>? ?? [];
    return PaginatedResult(
      items: rawItems.map((e) => itemFromJson(e as Map<String, dynamic>)).toList(),
      page: json['page'] as int? ?? 1,
      perPage: json['per_page'] as int? ?? 20,
      total: json['total'] as int? ?? 0,
      totalPages: json['total_pages'] as int? ?? 1,
    );
  }
}

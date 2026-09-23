/// Generic wrapper for Laravel API responses.
///
/// Real response bodies were not available (the handover Postman
/// collection has no saved example responses, and the "test" backend's
/// domain no longer resolves), so this follows the conventional Laravel
/// API Resource shape: a list endpoint returns `{"data": [...]}` (with
/// pagination metadata when paginated), a single-resource endpoint
/// returns `{"data": {...}}`. [unwrap]/[unwrapList] fall back to the raw
/// payload if there's no `data` key, so this keeps working either way.
/// Verify against the real API and adjust if it differs.
class PaginatedResponse<T> {
  PaginatedResponse({required this.items, this.currentPage, this.lastPage, this.total});

  factory PaginatedResponse.fromJson(
    Map<String, dynamic> json,
    T Function(Map<String, dynamic>) fromJson,
  ) {
    final rawList = unwrapList(json);
    final meta = json['meta'] is Map<String, dynamic>
        ? json['meta'] as Map<String, dynamic>
        : json;
    return PaginatedResponse<T>(
      items: rawList
          .whereType<Map<String, dynamic>>()
          .map(fromJson)
          .toList(),
      currentPage: meta['current_page'] as int?,
      lastPage: meta['last_page'] as int?,
      total: meta['total'] as int?,
    );
  }

  final List<T> items;
  final int? currentPage;
  final int? lastPage;
  final int? total;

  static Map<String, dynamic> unwrap(dynamic json) {
    if (json is Map<String, dynamic> && json['data'] is Map<String, dynamic>) {
      return json['data'] as Map<String, dynamic>;
    }
    return (json as Map<String, dynamic>?) ?? {};
  }

  static List<dynamic> unwrapList(dynamic json) {
    if (json is Map<String, dynamic> && json['data'] is List) {
      return json['data'] as List;
    }
    if (json is List) return json;
    return [];
  }
}

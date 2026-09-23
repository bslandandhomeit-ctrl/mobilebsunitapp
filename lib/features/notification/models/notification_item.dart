// Best-guess field names (no sample API response was available — see
// core/models/paginated_response.dart). Verify once the live API is reachable.
class NotificationItem {
  NotificationItem({
    required this.id,
    this.title,
    this.body,
    this.readAt,
    this.createdAt,
  });

  factory NotificationItem.fromJson(Map<String, dynamic> json) => NotificationItem(
    id: json['id'].toString(),
    title: json['title']?.toString(),
    body: (json['body'] ?? json['message'])?.toString(),
    readAt: json['read_at']?.toString(),
    createdAt: json['created_at']?.toString(),
  );

  final String id;
  final String? title;
  final String? body;
  final String? readAt;
  final String? createdAt;

  bool get isRead => readAt != null;
}

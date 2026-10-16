class NotificationItem {
  NotificationItem({
    required this.id,
    this.title,
    this.body,
    this.readAt,
    this.createdAt,
  });

  factory NotificationItem.fromJson(Map<String, dynamic> json) {
    final data = json['data'] is Map
        ? Map<String, dynamic>.from(json['data'] as Map)
        : <String, dynamic>{};
    final additional = data['additional_data'] is Map
        ? Map<String, dynamic>.from(data['additional_data'] as Map)
        : <String, dynamic>{};
    return NotificationItem(
      id: json['id'].toString(),
      title: (json['title'] ?? data['title'] ?? data['action'])?.toString(),
      body: (json['body'] ??
              json['message'] ??
              data['body'] ??
              data['message'] ??
              additional['unit_code'])
          ?.toString(),
      readAt: json['read_at']?.toString(),
      createdAt: json['created_at']?.toString(),
    );
  }

  final String id;
  final String? title;
  final String? body;
  final String? readAt;
  final String? createdAt;

  bool get isRead => readAt != null;
}

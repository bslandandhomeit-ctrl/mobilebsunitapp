// Verified against the local dev backend's DB schema (2026-09-11):
// `comments` table (polymorphic `commentable`). Photo attachments use
// Spatie Media Library on the backend (`Comment@media` relation) — no
// comments with attachments existed in the dev DB to sample a real
// response, so `mediaUrls` parsing is defensive/best-guess (tries the
// common Spatie accessor names) and simply comes back empty if the shape
// differs. Verify once real attachment data exists.
class UnitComment {
  UnitComment({required this.id, required this.content, this.createdAt, this.mediaUrls = const []});

  factory UnitComment.fromJson(Map<String, dynamic> json) => UnitComment(
    id: json['id'] as int,
    content: (json['content'] ?? '').toString(),
    createdAt: json['created_at']?.toString(),
    mediaUrls: _parseMediaUrls(json['media']),
  );

  final int id;
  final String content;
  final String? createdAt;
  final List<String> mediaUrls;

  static List<String> _parseMediaUrls(dynamic raw) {
    if (raw is! List) return const [];
    return raw
        .whereType<Map<String, dynamic>>()
        .map((m) => (m['original_url'] ?? m['url'] ?? '').toString())
        .where((u) => u.isNotEmpty)
        .toList();
  }
}

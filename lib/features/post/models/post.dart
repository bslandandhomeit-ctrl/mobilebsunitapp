// Verified against the local dev backend (2026-09-11): GET /api/posts
// and /api/posts/{id}. `content` is real HTML markup.
class Post {
  Post({
    required this.id,
    this.title,
    this.shortDescription,
    this.content,
    this.thumbnailImageUrl,
    this.featuredImageUrl,
    this.createdAt,
  });

  factory Post.fromJson(Map<String, dynamic> json) => Post(
    id: json['id'] as int,
    title: json['title']?.toString(),
    shortDescription: json['short_description']?.toString(),
    content: json['content']?.toString(),
    thumbnailImageUrl: json['thumbnail_image_url']?.toString(),
    featuredImageUrl: json['featured_image_url']?.toString(),
    createdAt: json['created_at']?.toString(),
  );

  final int id;
  final String? title;
  final String? shortDescription;
  final String? content;
  final String? thumbnailImageUrl;
  final String? featuredImageUrl;
  final String? createdAt;

  String? get excerpt => shortDescription;
  String? get thumbnailUrl => thumbnailImageUrl;
}

// Verified against the local dev backend source (app/Banner.php,
// 2019_04_30_142953_create_banners_table.php) — 2026-09-11. No live banner
// rows existed in the dev DB to sample directly, but the model/migration
// confirm these field names.
class BannerItem {
  BannerItem({required this.id, this.imageUrl, this.linkUrl});

  factory BannerItem.fromJson(Map<String, dynamic> json) => BannerItem(
    id: json['id'] as int,
    imageUrl: json['image_url']?.toString(),
    linkUrl: json['url']?.toString(),
  );

  final int id;
  final String? imageUrl;
  final String? linkUrl;
}

// Verified against the local dev backend (2026-09-11): nested under
// GET /api/projects and GET /api/projects/{id}/units.
class UnitType {
  UnitType({
    required this.id,
    required this.name,
    this.nameEn,
    this.projectId,
    this.category,
    this.featureImageUrl,
  });

  factory UnitType.fromJson(Map<String, dynamic> json) => UnitType(
    id: json['id'] as int,
    name: (json['name'] ?? '').toString(),
    nameEn: json['name_en']?.toString(),
    projectId: json['project_id'] as int?,
    category: json['category']?.toString(),
    featureImageUrl: json['feature_image_url']?.toString(),
  );

  final int id;
  final String name;
  final String? nameEn;
  final int? projectId;
  // Observed value: "HOUSE".
  final String? category;
  final String? featureImageUrl;

  String get displayName => nameEn?.isNotEmpty == true ? nameEn! : name;
}

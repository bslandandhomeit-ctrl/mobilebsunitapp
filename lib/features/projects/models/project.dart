import 'unit_type.dart';

// Verified against the local dev backend (2026-09-11): GET /api/projects.
class Project {
  Project({
    required this.id,
    required this.name,
    this.nameEn,
    this.shortCode,
    this.logoUrl,
    this.featureImageUrl,
    this.unitTypes = const [],
  });

  factory Project.fromJson(Map<String, dynamic> json) => Project(
    id: json['id'] as int,
    name: (json['name'] ?? '').toString(),
    nameEn: json['name_en']?.toString(),
    shortCode: json['short_code']?.toString(),
    logoUrl: json['logo_url']?.toString(),
    featureImageUrl: json['feature_image_url']?.toString(),
    unitTypes: (json['unit_types'] as List?)
            ?.whereType<Map<String, dynamic>>()
            .map(UnitType.fromJson)
            .toList() ??
        const [],
  );

  final int id;
  final String name;
  final String? nameEn;
  final String? shortCode;
  final String? logoUrl;
  final String? featureImageUrl;
  final List<UnitType> unitTypes;

  /// English name when available, falling back to the (Khmer) [name].
  String get displayName => nameEn?.isNotEmpty == true ? nameEn! : name;
}

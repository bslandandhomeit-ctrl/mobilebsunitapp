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
    this.promotionAmount,
  });

  factory UnitType.fromJson(Map<String, dynamic> json) => UnitType(
    id: json['id'] as int,
    name: (json['name'] ?? '').toString(),
    nameEn: json['name_en']?.toString(),
    projectId: json['project_id'] as int?,
    category: json['category']?.toString(),
    featureImageUrl: json['feature_image_url']?.toString(),
    promotionAmount: _promotionAmount(json['active_discount_promotions']),
  );

  // The running discount promotion (embed unitTypes.activeDiscountPromotions), as the
  // unit list reads it: the first one's amount.
  static double? _promotionAmount(dynamic promotions) {
    if (promotions is! List || promotions.isEmpty || promotions.first is! Map) {
      return null;
    }
    final amount = num.tryParse('${(promotions.first as Map)['amount']}');
    return amount == null || amount <= 0 ? null : amount.toDouble();
  }

  final int id;
  final String name;
  final String? nameEn;
  final int? projectId;
  // Observed value: "HOUSE".
  final String? category;
  final String? featureImageUrl;
  // Discount of the running promotion, shown as the red "-$3,000" corner ribbon.
  final double? promotionAmount;

  String get displayName => nameEn?.isNotEmpty == true ? nameEn! : name;
}

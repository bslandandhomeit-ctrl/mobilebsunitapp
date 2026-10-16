class Unit {
  Unit({
    required this.id,
    required this.code,
    this.unitTypeId,
    this.price,
    this.availabilityStatus,
    this.projectName,
    this.unitTypeName,
    this.previewUrl,
    this.buildingArea,
    this.landArea,
    this.buildingWidth,
    this.buildingLength,
    this.landWidth,
    this.landLength,
    this.bedrooms,
    this.bathrooms,
    this.livingRooms,
    this.kitchens,
    this.swimmingPools,
    this.floor,
    this.promotionAmount,
    this.promotionEndDate,
    this.exteriorUrls = const [],
    this.interiorUrls = const [],
    this.floorPlanUrls = const [],
  });

  factory Unit.fromJson(Map<String, dynamic> json) {
    final type = json['unit_type'] as Map<String, dynamic>? ?? {};
    final project = type['project'] as Map<String, dynamic>? ?? {};
    final media = (type['media'] as List? ?? [])
        .whereType<Map<String, dynamic>>();
    List<String> images(String collection) => media
        .where((m) => m['collection_name'] == collection)
        .map((m) => m['url']?.toString() ?? '')
        .where((url) => url.isNotEmpty)
        .toList();
    String? name(Map<String, dynamic> data) =>
        (data['name_en']?.toString().isNotEmpty == true
                ? data['name_en']
                : data['name'])
            ?.toString();
    double? number(dynamic value) =>
        value == null ? null : double.tryParse('$value');
    final promotions = (type['active_discount_promotions'] as List? ?? [])
        .whereType<Map<String, dynamic>>();
    return Unit(
      id: (json['id'] as num).toInt(),
      code: (json['code'] ?? '').toString(),
      unitTypeId: (json['unit_type_id'] as num?)?.toInt(),
      price: number(json['price']),
      availabilityStatus: json['availability_status']?.toString(),
      projectName: name(project),
      unitTypeName: name(type),
      previewUrl: (json['feature_image_url'] ?? type['feature_image_url'])
          ?.toString(),
      buildingArea: number(json['building_area']),
      landArea: number(json['land_area']),
      buildingWidth: number(json['building_size_width']),
      buildingLength: number(json['building_size_length']),
      landWidth: number(json['land_size_width']),
      landLength: number(json['land_size_length']),
      bedrooms: number(json['bedroom']),
      bathrooms: number(json['bathroom']),
      livingRooms: number(json['living_room']),
      kitchens: number(json['kitchen']),
      swimmingPools: number(json['swimming_pool']),
      floor: json['floor']?.toString(),
      promotionAmount: promotions.isEmpty
          ? 0
          : number(promotions.first['amount']),
      promotionEndDate: promotions.isEmpty
          ? null
          : DateTime.tryParse('${promotions.first['end_date'] ?? ''}'),
      exteriorUrls: images('EXTERIOR'),
      interiorUrls: images('INTERIOR'),
      floorPlanUrls: images('FLOOR_PLAN'),
    );
  }
  final String? floor;
  final int id;
  final String code;
  final int? unitTypeId;
  final double? price;
  final String? availabilityStatus, projectName, unitTypeName, previewUrl;
  final double? buildingArea, landArea;
  final double? buildingWidth, buildingLength, landWidth, landLength;
  final double? bedrooms,
      bathrooms,
      livingRooms,
      kitchens,
      swimmingPools,
      promotionAmount;
  // Last day of the running discount promotion (null when there is none).
  final DateTime? promotionEndDate;
  final List<String> exteriorUrls, interiorUrls, floorPlanUrls;

  /// Price after the running promotion, or null when there is no discount.
  double? get promotionPrice {
    final discount = promotionAmount ?? 0;
    if (price == null || discount <= 0) return null;
    return price! - discount;
  }

  /// "Valid until" date shown with a promotion: the end of the current month,
  /// or the promotion's own end date when it ends before that.
  DateTime? promotionValidUntil([DateTime? today]) {
    if (promotionPrice == null) return null;
    final now = today ?? DateTime.now();
    final monthEnd = DateTime(now.year, now.month + 1, 0);
    final end = promotionEndDate;
    if (end == null) return monthEnd;
    final endDay = DateTime(end.year, end.month, end.day);
    return endDay.isBefore(monthEnd) ? endDay : monthEnd;
  }

  String get name => code;
  String? get status => availabilityStatus;
}

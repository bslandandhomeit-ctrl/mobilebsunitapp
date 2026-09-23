// Verified against the local dev backend (2026-09-11): GET
// /api/projects/{id}/units. Real status field is `availability_status`
// (not `status`), and the unit's label is `code` (not `name`).
class Unit {
  Unit({
    required this.id,
    required this.code,
    this.unitTypeId,
    this.price,
    this.availabilityStatus,
  });

  factory Unit.fromJson(Map<String, dynamic> json) => Unit(
    id: json['id'] as int,
    code: (json['code'] ?? '').toString(),
    unitTypeId: json['unit_type_id'] as int?,
    price: (json['price'] as num?)?.toDouble(),
    availabilityStatus: json['availability_status']?.toString(),
  );

  final int id;
  final String code;
  final int? unitTypeId;
  final double? price;
  // Observed values include AVAILABLE, UNAVAILABLE, HOLD, DEPOSIT, CONTRACT,
  // HANDOVERED (per the Postman collection's filter params + live data).
  final String? availabilityStatus;

  String get name => code;
  String? get status => availabilityStatus;
}

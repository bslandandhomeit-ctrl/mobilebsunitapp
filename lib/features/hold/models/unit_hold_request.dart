// Verified against the local dev backend's DB schema (2026-09-11):
// `unit_hold_requests` table. `unitCode` comes from the nested `unit`
// object (requested via `embed=...,unit,...`) — same `code` field
// confirmed on the Unit model itself.
class UnitHoldRequest {
  UnitHoldRequest({
    required this.id,
    this.details = const {},
    this.unitId,
    this.unitCode,
    this.status,
    this.createdAt,
  });

  factory UnitHoldRequest.fromJson(Map<String, dynamic> json) =>
      UnitHoldRequest(
        id: json['id'] as int,
        details: json,
        unitId: json['unit_id'] as int?,
        unitCode: (json['unit'] as Map<String, dynamic>?)?['code']?.toString(),
        status: json['status']?.toString(),
        createdAt: json['created_at']?.toString(),
      );

  final Map<String, dynamic> details;
  final int id;
  final int? unitId;
  final String? unitCode;
  final String? status;
  final String? createdAt;
}

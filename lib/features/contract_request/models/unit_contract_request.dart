// Verified against the local dev backend's DB schema (2026-09-11):
// `unit_contract_requests` table. `unitCode` comes from the nested `unit`
// object (requested via `embed=...,unit,...`).
class UnitContractRequest {
  UnitContractRequest({
    required this.id,
    this.unitId,
    this.unitCode,
    this.status,
    this.contractId,
    this.createdAt,
  });

  factory UnitContractRequest.fromJson(Map<String, dynamic> json) => UnitContractRequest(
    id: json['id'] as int,
    unitId: json['unit_id'] as int?,
    unitCode: (json['unit'] as Map<String, dynamic>?)?['code']?.toString(),
    status: json['status']?.toString(),
    contractId: json['contract_id'] as int?,
    createdAt: json['created_at']?.toString(),
  );

  final int id;
  final int? unitId;
  final String? unitCode;
  final String? status;
  // Set once the request is finalized into an actual signed Contract —
  // that's what /api/contract/{id}/pdf needs, not this request's own id.
  final int? contractId;
  final String? createdAt;
}

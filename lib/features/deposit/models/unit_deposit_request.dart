// Verified against the local dev backend's DB schema (2026-09-11):
// `unit_deposit_requests` table + Api/UnitDepositRequestController's
// validation rules. Real amount field is `deposit_amount`, not `amount`.
// `unitCode` comes from the nested `unit` object (requested via
// `embed=...,unit`).
class UnitDepositRequest {
  UnitDepositRequest({
    required this.id,
    this.userId,
    this.unitId,
    this.unitCode,
    this.status,
    this.depositAmount,
    this.otherDiscountAllowance,
    this.createdAt,
  });

  factory UnitDepositRequest.fromJson(Map<String, dynamic> json) => UnitDepositRequest(
    id: json['id'] as int,
    userId: json['user_id'] as int?,
    unitId: json['unit_id'] as int?,
    unitCode: (json['unit'] as Map<String, dynamic>?)?['code']?.toString(),
    status: json['status']?.toString(),
    depositAmount: (json['deposit_amount'] as num?)?.toDouble(),
    otherDiscountAllowance: (json['other_discount_allowance'] as num?)?.toDouble(),
    createdAt: json['created_at']?.toString(),
  );

  final int id;
  final int? userId;
  final int? unitId;
  final String? unitCode;
  final String? status;
  final double? depositAmount;
  final double? otherDiscountAllowance;
  final String? createdAt;

  /// Both `isContractRequestable()` and `isChangeable()` on the backend
  /// restrict those actions to the deposit's own creator.
  bool isOwnedBy(int userId) => this.userId == userId;
}

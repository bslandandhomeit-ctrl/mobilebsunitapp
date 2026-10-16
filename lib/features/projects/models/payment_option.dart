// Verified against the local dev backend's DB schema (2026-09-11):
// `payment_options` table.
class PaymentOption {
  PaymentOption({
    required this.id,
    required this.name,
    this.unitTypeId,
    this.depositAmount,
    this.loanDuration,
    this.interest,
    this.specialDiscount,
    this.isFirstPayment = false,
    this.firstPaymentPercentage,
    this.firstPaymentDuration,
  });

  factory PaymentOption.fromJson(Map<String, dynamic> json) => PaymentOption(
    id: json['id'] as int,
    name: (json['name'] ?? '').toString(),
    unitTypeId: json['unit_type_id'] as int?,
    depositAmount: (json['deposit_amount'] as num?)?.toDouble(),
    loanDuration: json['loan_duration'] as int?,
    interest: (json['interest'] as num?)?.toDouble(),
    specialDiscount: double.tryParse('${json['special_discount']}'),
    isFirstPayment:
        json['is_first_payment'] == true || json['is_first_payment'] == 1,
    firstPaymentPercentage: double.tryParse(
      '${json['first_payment_percentage']}',
    ),
    firstPaymentDuration: (json['first_payment_duration'] as num?)?.toInt(),
  );

  final int id;
  final String name;
  final int? unitTypeId;
  final double? depositAmount;
  final int? loanDuration;
  final double? interest;
  final double? specialDiscount;
  final bool isFirstPayment;
  final double? firstPaymentPercentage;
  final int? firstPaymentDuration;
}

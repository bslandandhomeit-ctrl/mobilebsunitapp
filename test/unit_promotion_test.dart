import 'package:bs_contract_flutter/features/projects/models/unit.dart';
import 'package:flutter_test/flutter_test.dart';

Unit unit({
  double? price = 50000,
  double amount = 3000,
  String? end = '2027-12-31 00:00:00',
}) => Unit.fromJson({
  'id': 1,
  'code': 'A-1',
  'price': price,
  'unit_type': {
    'active_discount_promotions': [
      if (amount > 0) {'amount': amount, 'end_date': end},
    ],
  },
});

void main() {
  test('promotion price is price minus the discount', () {
    expect(unit().promotionPrice, 47000);
    expect(unit(amount: 0).promotionPrice, isNull);
  });

  test(
    'valid until is the end of the current month while the promotion runs',
    () {
      expect(
        unit().promotionValidUntil(DateTime(2026, 10, 16)),
        DateTime(2026, 10, 31),
      );
      expect(
        unit().promotionValidUntil(DateTime(2026, 11, 2)),
        DateTime(2026, 11, 30),
      );
      expect(
        unit().promotionValidUntil(DateTime(2026, 12, 31)),
        DateTime(2026, 12, 31),
      );
    },
  );

  test(
    'valid until is the promotion end when it ends before the month does',
    () {
      expect(
        unit(end: '2026-10-20 00:00:00')
            .promotionValidUntil(DateTime(2026, 10, 16)),
        DateTime(2026, 10, 20),
      );
    },
  );

  test('no valid until without a promotion', () {
    expect(unit(amount: 0).promotionValidUntil(DateTime(2026, 10, 16)), isNull);
  });
}

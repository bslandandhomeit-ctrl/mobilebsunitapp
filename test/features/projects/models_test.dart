import 'package:bs_contract_flutter/core/models/app_version.dart';
import 'package:bs_contract_flutter/features/deposit/models/unit_deposit_request.dart';
import 'package:bs_contract_flutter/features/hold/models/unit_hold_request.dart';
import 'package:bs_contract_flutter/features/projects/models/unit.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('Unit.fromJson', () {
    test('reads code as the display name and availability_status as status', () {
      final unit = Unit.fromJson({
        'id': 12,
        'code': 'A-101',
        'unit_type_id': 3,
        'price': 85000,
        'availability_status': 'AVAILABLE',
      });

      expect(unit.id, 12);
      expect(unit.name, 'A-101');
      expect(unit.status, 'AVAILABLE');
      expect(unit.price, 85000.0);
    });

    test('defaults code to an empty string when missing', () {
      final unit = Unit.fromJson({'id': 1});
      expect(unit.code, '');
      expect(unit.status, isNull);
    });
  });

  group('UnitHoldRequest.fromJson', () {
    test('reads the unit code from the nested unit object', () {
      final request = UnitHoldRequest.fromJson({
        'id': 5,
        'unit_id': 12,
        'status': 'PENDING',
        'unit': {'code': 'A-101'},
      });

      expect(request.unitCode, 'A-101');
      expect(request.status, 'PENDING');
    });

    test('leaves unitCode null when there is no embedded unit', () {
      final request = UnitHoldRequest.fromJson({'id': 5});
      expect(request.unitCode, isNull);
    });
  });

  group('UnitDepositRequest', () {
    test('fromJson reads deposit_amount, not amount', () {
      final request = UnitDepositRequest.fromJson({
        'id': 9,
        'user_id': 4,
        'deposit_amount': 2500,
        'other_discount_allowance': 100,
      });

      expect(request.depositAmount, 2500.0);
      expect(request.otherDiscountAllowance, 100.0);
    });

    test('isOwnedBy compares against user_id', () {
      final request = UnitDepositRequest.fromJson({'id': 1, 'user_id': 4});
      expect(request.isOwnedBy(4), isTrue);
      expect(request.isOwnedBy(5), isFalse);
    });
  });

  group('AppVersion.fromJson', () {
    test('treats forced_update as either a bool or a 1/0 int', () {
      expect(AppVersion.fromJson({'build': 1, 'version': '1.0', 'forced_update': true}).forcedUpdate, isTrue);
      expect(AppVersion.fromJson({'build': 1, 'version': '1.0', 'forced_update': 1}).forcedUpdate, isTrue);
      expect(AppVersion.fromJson({'build': 1, 'version': '1.0', 'forced_update': 0}).forcedUpdate, isFalse);
      expect(AppVersion.fromJson({'build': 1, 'version': '1.0'}).forcedUpdate, isFalse);
    });
  });
}

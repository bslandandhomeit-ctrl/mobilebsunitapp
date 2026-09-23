import 'package:bs_contract_flutter/features/auth/models/user.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('AppUser.fromJson', () {
    test('parses a whoami response with roles as plain strings', () {
      final user = AppUser.fromJson({
        'id': 7,
        'name': 'Sok Dara',
        'phone_number': '0101234567',
        'email': 'dara@example.com',
        'gender': 'Male',
        'birthdate': '1990-05-01',
        'roles': ['unit_controller', 'agent'],
      });

      expect(user.id, 7);
      expect(user.name, 'Sok Dara');
      expect(user.phone, '0101234567');
      expect(user.roles, ['unit_controller', 'agent']);
      expect(user.birthdate, DateTime.parse('1990-05-01'));
    });

    test('parses roles as embedded objects with a name field', () {
      final user = AppUser.fromJson({
        'id': 1,
        'name': 'Manager',
        'roles': [
          {'name': 'sale_manager'},
        ],
      });

      expect(user.roles, ['sale_manager']);
      expect(user.hasRole('sale_manager'), isTrue);
    });

    test('tolerates missing optional fields (N/A gender, empty birthdate)', () {
      final user = AppUser.fromJson({
        'id': 2,
        'name': 'New User',
        'gender': 'N/A',
        'birthdate': '',
      });

      expect(user.gender, 'N/A');
      expect(user.birthdate, isNull);
      expect(user.roles, isEmpty);
    });

    test('throws when given the un-unwrapped {"data": {...}} envelope',
        () {
      // Regression guard for the real on-device crash: passing the raw
      // envelope instead of PaginatedResponse.unwrap(response.data) made
      // json['id'] null, throwing "type 'Null' is not a subtype of type
      // 'int'" instead of navigating to Home.
      expect(
        () => AppUser.fromJson({
          'data': {'id': 7, 'name': 'Sok Dara'},
        }),
        throwsA(isA<TypeError>()),
      );
    });
  });

  group('AppUser permission gates', () {
    AppUser withRoles(List<String> roles) =>
        AppUser(id: 1, name: 'Test', roles: roles);

    test('canApproveHold is unit_controller only', () {
      expect(withRoles(['unit_controller']).canApproveHold, isTrue);
      expect(withRoles(['sale_manager']).canApproveHold, isFalse);
    });

    test('canApproveDeposit allows unit_controller or sale_manager', () {
      expect(withRoles(['unit_controller']).canApproveDeposit, isTrue);
      expect(withRoles(['sale_manager']).canApproveDeposit, isTrue);
      expect(withRoles(['agent']).canApproveDeposit, isFalse);
    });

    test('canCancelDeposit is unit_controller only, unlike approve', () {
      expect(withRoles(['sale_manager']).canCancelDeposit, isFalse);
      expect(withRoles(['unit_controller']).canCancelDeposit, isTrue);
    });
  });
}

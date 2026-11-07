import 'package:flutter_test/flutter_test.dart';
import 'package:bs_contract_flutter/features/deposit/models/unit_deposit_request.dart';

UnitDepositRequest _r(Map<String, dynamic> extra) =>
    UnitDepositRequest.fromJson({'id': 1, 'status': 'PENDING', ...extra});

void main() {
  test('sale manager buttons hide after their own decision', () {
    final r = _r({'unit_controller_status': 'PENDING', 'sale_manager_status': 'APPROVED'});
    expect(r.awaitsDecisionFrom(saleManager: true, unitController: false), isFalse);
    expect(r.awaitsDecisionFrom(saleManager: false, unitController: true), isTrue);
  });

  test('rejected by the viewer also hides the buttons', () {
    final r = _r({'unit_controller_status': 'PENDING', 'sale_manager_status': 'REJECTED'});
    expect(r.awaitsDecisionFrom(saleManager: true, unitController: false), isFalse);
  });

  test('still pending for the viewer keeps the buttons', () {
    final r = _r({'unit_controller_status': 'PENDING', 'sale_manager_status': 'PENDING'});
    expect(r.awaitsDecisionFrom(saleManager: true, unitController: false), isTrue);
  });

  test('missing per-role statuses keep the old behaviour', () {
    expect(_r({}).awaitsDecisionFrom(saleManager: true, unitController: false), isTrue);
  });

  test('viewer with both roles sees buttons while either is pending', () {
    final r = _r({'unit_controller_status': 'APPROVED', 'sale_manager_status': 'PENDING'});
    expect(r.awaitsDecisionFrom(saleManager: true, unitController: true), isTrue);
  });
}

import 'package:flutter_test/flutter_test.dart';
import 'package:bs_contract_flutter/features/notification/models/notification_item.dart';

void main() {
  test('reads original database notification action and unit code', () {
    final item = NotificationItem.fromJson({
      'id': 'synthetic',
      'data': {
        'action': 'Overdue',
        'type': 'unit_deposit_request',
        'additional_data': {'unit_code': 'L1-TEST'},
      },
      'read_at': null,
    });
    expect(item.title, 'Overdue');
    expect(item.body, 'L1-TEST');
    expect(item.isRead, isFalse);
  });

  test('keeps explicit text and read state', () {
    final item = NotificationItem.fromJson({
      'id': 'synthetic',
      'title': 'Explicit title',
      'message': 'Explicit body',
      'data': {'action': 'Overdue'},
      'read_at': '2026-10-09 10:00:00',
    });
    expect(item.title, 'Explicit title');
    expect(item.body, 'Explicit body');
    expect(item.isRead, isTrue);
  });

  test('accepts missing nested payload', () {
    final item = NotificationItem.fromJson({'id': 'synthetic'});
    expect(item.title, isNull);
    expect(item.body, isNull);
  });
}

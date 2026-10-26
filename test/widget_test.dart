import 'package:flutter_test/flutter_test.dart';

import 'package:bs_contract_flutter/main.dart';

void main() {
  testWidgets('App boots to the splash screen', (WidgetTester tester) async {
    await tester.pumpWidget(const BsUnitsApp());
    await tester.pump();

    expect(find.text('BS Units'), findsOneWidget);

    // Let the splash redirect timer finish so no timers remain pending.
    await tester.pump(const Duration(milliseconds: 2000));
  });
}

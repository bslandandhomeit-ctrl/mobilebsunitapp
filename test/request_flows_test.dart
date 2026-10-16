import 'package:bs_contract_flutter/app/theme/app_theme.dart';
import 'package:bs_contract_flutter/core/network/api_client.dart';
import 'package:bs_contract_flutter/features/deposit/views/deposit_request_form_view.dart';
import 'package:bs_contract_flutter/features/hold/views/hold_request_form_view.dart';
import 'package:bs_contract_flutter/features/projects/models/unit.dart';
import 'package:bs_contract_flutter/l10n/app_localizations.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart' hide Response;

final unit = Unit(
  id: 12,
  code: 'L1-B68',
  unitTypeId: 5,
  price: 103500,
  projectName: 'Borey Chaktomuk City 2',
  unitTypeName: 'L1',
  promotionAmount: 250,
  availabilityStatus: 'AVAILABLE',
);
Widget app(Widget child) => GetMaterialApp(
  theme: AppTheme.light,
  locale: const Locale('en'),
  localizationsDelegates: const [
    AppLocalizations.delegate,
    GlobalMaterialLocalizations.delegate,
    GlobalWidgetsLocalizations.delegate,
    GlobalCupertinoLocalizations.delegate,
  ],
  supportedLocales: AppLocalizations.supportedLocales,
  home: child,
);
Future<void> fill(WidgetTester tester, String label, String value) async {
  final field = find.widgetWithText(TextFormField, label);
  await tester.ensureVisible(field);
  await tester.enterText(field, value);
  await tester.pumpAndSettle();
}

Future<void> tap(WidgetTester tester, String text) async {
  await tester.tap(find.text(text).last);
  await tester.pumpAndSettle();
  if (text == 'Save') {
    await tester.pump(const Duration(seconds: 4));
    await tester.pumpAndSettle();
  }
}

Future<void> customers(WidgetTester tester) async {
  await fill(tester, 'Surname and Name (Customer 1) *', 'Local Tester');
  await tester.ensureVisible(find.byKey(const ValueKey('gender-false')));
  await tester.tap(find.byKey(const ValueKey('gender-false')));
  await tester.pumpAndSettle();
  await tap(tester, 'Male');
  await fill(tester, 'Line 1 *', '012345678');
  await fill(tester, 'Line 2', '098765432');
  await tap(tester, 'Next');
  await tap(tester, 'Next');
  await fill(tester, 'Deposit amount (\$) *', '500');
  await tap(tester, 'Next');
}

void main() {
  Map<String, dynamic>? sent;
  setUp(() {
    Get.testMode = true;
    sent = null;
    final dio = ApiClient.instance.dio;
    dio.interceptors.clear();
    dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (o, h) {
          if (o.method == 'POST') {
            sent = Map<String, dynamic>.from(o.data as Map);
            h.resolve(
              Response(
                requestOptions: o,
                statusCode: 200,
                data: {
                  'data': {'id': 99, 'unit_id': 12, 'status': 'PENDING'},
                },
              ),
            );
          } else {
            h.resolve(
              Response(
                requestOptions: o,
                statusCode: 200,
                data: {
                  'data': [
                    {
                      'id': 10,
                      'name': 'Standard plan',
                      'loan_duration': 120,
                      'interest': 6,
                      'special_discount': 2,
                      'is_first_payment': 1,
                      'first_payment_percentage': 20,
                      'first_payment_duration': 6,
                    },
                  ],
                },
              ),
            );
          }
        },
      ),
    );
  });
  tearDown(() async {
    Get.reset();
  });
  testWidgets('Required customer fields prevent advancing or submitting', (
    tester,
  ) async {
    await tester.pumpWidget(app(DepositRequestFormView(unit: unit)));
    await tester.pumpAndSettle();
    await tap(tester, 'Next');
    expect(find.text('1 / 4'), findsOneWidget);
    expect(sent, isNull);
    await fill(tester, 'Line 1 *', '123');
    await tap(tester, 'Next');
    expect(
      find.text('Enter a phone number starting with 0 (9–10 digits).'),
      findsOneWidget,
    );
  });
  testWidgets(
    'None payment option and second phone persist without a second customer',
    (tester) async {
      await tester.pumpWidget(app(DepositRequestFormView(unit: unit)));
      await tester.pumpAndSettle();
      await customers(tester);
      await tap(tester, 'Save');
      expect(sent!['is_selected_payment_option'], 0);
      expect(sent!['customer_phone_number2'], '098765432');
      expect(sent!.containsKey('customer2_name'), isFalse);
      expect(sent!['deposit_amount'], 500);
      expect(
        sent!.containsKey('discount_promotion'),
        isFalse,
      ); // Backend calculates promotions.
    },
  );
  testWidgets(
    'Custom terms submit months and down payment amount, not percentage',
    (tester) async {
      await tester.pumpWidget(app(DepositRequestFormView(unit: unit)));
      await tester.pumpAndSettle();
      await customers(tester);
      await tester.tap(find.byKey(const ValueKey('payment-0')));
      await tester.pumpAndSettle();
      await tap(tester, 'Other');
      final down = find.byType(DropdownButtonFormField<bool>);
      await tester.ensureVisible(down);
      await tester.pumpAndSettle();
      await tester.tap(down);
      await tester.pumpAndSettle();
      await tap(tester, 'Yes');
      await fill(tester, 'Down Payment Amount (\$)', '20000');
      await fill(tester, 'First payment duration (months)', '6');
      await fill(tester, 'Annual interest rate (%)', '6');
      await fill(tester, 'Installment Duration (months)', '120');
      await tap(tester, 'Save');
      expect(sent!['is_selected_payment_option'], 1);
      expect(sent!['loan_duration'], 120);
      expect(sent!['first_payment_duration'], 6);
      expect(sent!['first_payment_amount'], 20000);
      expect(sent!.containsKey('first_payment_percentage'), isFalse);
    },
  );
  testWidgets(
    'Preset payment option submits its ID without custom term overrides',
    (tester) async {
      await tester.pumpWidget(app(DepositRequestFormView(unit: unit)));
      await tester.pumpAndSettle();
      await customers(tester);
      await tester.tap(find.byKey(const ValueKey('payment-0')));
      await tester.pumpAndSettle();
      await tap(tester, 'Standard plan');
      await tap(tester, 'Save');
      expect(sent!['payment_option_id'], 10);
      expect(sent!.containsKey('loan_duration'), isFalse);
    },
  );
  testWidgets(
    'Hold has only remark input and submits the retained API duration',
    (tester) async {
      await tester.pumpWidget(app(HoldRequestFormView(unit: unit)));
      await tester.pumpAndSettle();
      expect(find.byType(TextFormField), findsOneWidget);
      expect(find.byType(Slider), findsNothing);
      await fill(tester, 'Remark (optional)', 'Please hold this unit.');
      await tester.ensureVisible(find.text('Save'));
      await tap(tester, 'Save');
      expect(sent, {
        'unit_id': 12,
        'hold_day': 3,
        'remark': 'Please hold this unit.',
      });
    },
  );
  test('Unit API parses dimensions, nested project and media collections', () {
    final u = Unit.fromJson({
      'id': 1,
      'code': 'L1-B68',
      'price': '103500.00',
      'building_size_width': '5',
      'building_size_length': '12',
      'bedroom': 3,
      'swimming_pool': null,
      'unit_type': {
        'name': 'L1',
        'project': {'name_en': 'Borey Chaktomuk City 2'},
        'media': [
          {
            'collection_name': 'INTERIOR',
            'url': 'https://example.test/interior.jpg',
          },
          {
            'collection_name': 'FLOOR_PLAN',
            'url': 'https://example.test/plan.jpg',
          },
        ],
      },
    });
    expect(u.projectName, 'Borey Chaktomuk City 2');
    expect(u.price, 103500);
    expect(u.buildingWidth, 5);
    expect(u.swimmingPools, isNull);
    expect(u.interiorUrls, ['https://example.test/interior.jpg']);
    expect(u.floorPlanUrls, ['https://example.test/plan.jpg']);
  });
}

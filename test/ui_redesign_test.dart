import 'dart:io';
import 'dart:convert';

import 'package:bs_contract_flutter/core/network/api_exception.dart';
import 'package:bs_contract_flutter/features/contract_request/data/contract_request_repository.dart';

import 'package:bs_contract_flutter/features/contract_request/views/contract_request_detail_view.dart';

import 'package:bs_contract_flutter/features/requests/views/request_detail_view.dart';

import 'dart:ui' as ui;

import 'package:bs_contract_flutter/app/theme/app_theme.dart';
import 'package:bs_contract_flutter/app/views/main_shell.dart';
import 'package:bs_contract_flutter/core/widgets/app_ui.dart';
import 'package:bs_contract_flutter/core/network/api_client.dart';
import 'package:bs_contract_flutter/features/auth/views/change_password_view.dart';
import 'package:bs_contract_flutter/features/auth/views/members_view.dart';
import 'package:bs_contract_flutter/features/projects/views/unit_detail_view.dart';
import 'package:bs_contract_flutter/features/projects/views/unit_type_detail_view.dart';
import 'package:bs_contract_flutter/features/projects/views/unit_search_view.dart';
import 'package:bs_contract_flutter/features/projects/views/floor_plan_view.dart';
import 'package:bs_contract_flutter/features/post/views/post_detail_view.dart';
import 'package:dio/dio.dart';
import 'package:bs_contract_flutter/features/auth/controllers/auth_controller.dart';
import 'package:bs_contract_flutter/features/auth/models/user.dart';
import 'package:bs_contract_flutter/features/auth/views/forgot_password_view.dart';
import 'package:bs_contract_flutter/features/auth/views/login_view.dart';
import 'package:bs_contract_flutter/features/auth/views/profile_view.dart';
import 'package:bs_contract_flutter/features/deposit/controllers/deposit_controller.dart';
import 'package:bs_contract_flutter/features/deposit/models/unit_deposit_request.dart';
import 'package:bs_contract_flutter/features/deposit/views/change_unit_deposit_view.dart';
import 'package:bs_contract_flutter/features/deposit/views/deposit_request_form_view.dart';
import 'package:bs_contract_flutter/features/deposit/views/deposit_view.dart';
import 'package:bs_contract_flutter/features/contract_request/views/contract_request_form_view.dart';
import 'package:bs_contract_flutter/features/hold/views/hold_request_form_view.dart';
import 'package:bs_contract_flutter/features/home/controllers/home_controller.dart';
import 'package:bs_contract_flutter/features/more/views/document_scanner_view.dart';
import 'package:bs_contract_flutter/features/more/views/loan_calculator_view.dart';
import 'package:bs_contract_flutter/features/more/views/more_view.dart';
import 'package:bs_contract_flutter/features/more/views/support_center_view.dart';
import 'package:bs_contract_flutter/features/projects/controllers/project_detail_controller.dart';
import 'package:bs_contract_flutter/features/projects/models/project.dart';
import 'package:bs_contract_flutter/features/projects/models/unit.dart';
import 'package:bs_contract_flutter/features/projects/models/unit_type.dart';
import 'package:bs_contract_flutter/features/projects/views/project_detail_view.dart';
import 'package:bs_contract_flutter/l10n/app_localizations.dart';
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart' hide Response;

class _Auth extends AuthController {
  @override
  // Keep UI tests independent of network and native plugin startup.
  // ignore: must_call_super
  void onInit() {}
}

class _Home extends HomeController {
  @override
  // Keep UI tests independent of network and native plugin startup.
  // ignore: must_call_super
  void onInit() {}
}

class _Deposits extends DepositController {
  @override
  Future<void> load() async {}
  @override
  // Keep UI tests independent of network and native plugin startup.
  // ignore: must_call_super
  void onInit() {}
}

class _Project extends ProjectDetailController {
  _Project(super.project);
  @override
  // Keep UI tests independent of network and native plugin startup.
  // ignore: must_call_super
  void onInit() {}
}

final _unit = Unit(
  id: 1,
  code: 'BCC1-A-001',
  price: 65000,
  availabilityStatus: 'AVAILABLE',
);
final _unitJson = <String, dynamic>{
  'id': 1,
  'code': 'BCC1-A-001',
  'price': 65000,
  'unit_type_id': 1,
  'availability_status': 'AVAILABLE',
  'building_size_width': 5,
  'building_size_length': 12,
  'land_size_width': 5,
  'land_size_length': 20,
  'bedroom': 3,
  'bathroom': 2,
  'living_room': 1,
  'kitchen': 1,
  'swimming_pool': null,
  'unit_type': {
    'name': 'L1',
    'project': {'name_en': 'Borey Chaktomuk City 1'},
  },
};
final _deposit = UnitDepositRequest(
  id: 1,
  userId: 1,
  unitId: 1,
  unitCode: 'BCC1-A-001',
  status: 'APPROVED',
);
final _boundaryKey = GlobalKey();

Widget _app(
  Widget page, {
  String language = 'en',
  double scale = 1,
  double keyboard = 0,
}) => GetMaterialApp(
  theme: AppTheme.light,
  locale: Locale(language),
  supportedLocales: AppLocalizations.supportedLocales,
  localizationsDelegates: const [
    AppLocalizations.delegate,
    GlobalMaterialLocalizations.delegate,
    GlobalWidgetsLocalizations.delegate,
    GlobalCupertinoLocalizations.delegate,
  ],
  builder: (context, child) => MediaQuery(
    data: MediaQuery.of(context).copyWith(
      textScaler: TextScaler.linear(scale),
      viewInsets: EdgeInsets.only(bottom: keyboard),
    ),
    child: child!,
  ),
  home: RepaintBoundary(key: _boundaryKey, child: page),
);

Future<void> _capture(WidgetTester tester, String name) async {
  const output = String.fromEnvironment('UI_PREVIEW_DIR');
  if (output.isEmpty) return;
  await tester.runAsync(() async {
    final boundary =
        _boundaryKey.currentContext!.findRenderObject()!
            as RenderRepaintBoundary;
    final image = await boundary.toImage(pixelRatio: 2);
    final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
    await Directory(output).create(recursive: true);
    await File('$output/$name.png').writeAsBytes(bytes!.buffer.asUint8List());
    image.dispose();
  });
}

void main() {
  test('Contract attachment URLs preserve absolute URLs and storage paths', () {
    expect(
      contractAttachmentUrl(
        'https://example.com/storage/unit_contract_request/a.jpg',
      ),
      'https://example.com/storage/unit_contract_request/a.jpg',
    );
    expect(
      contractAttachmentUrl('storage/unit_contract_request/a.jpg'),
      endsWith('/storage/unit_contract_request/a.jpg'),
    );
    expect(
      contractAttachmentUrl('unit_contract_request/a.jpg'),
      endsWith('/storage/unit_contract_request/a.jpg'),
    );
  });

  setUpAll(() async {
    // Optional real fonts for local visual previews. Layout checks also run in CI without them.
    const font = String.fromEnvironment('UI_PREVIEW_FONT');
    if (font.isNotEmpty) {
      final loader = FontLoader('Roboto')
        ..addFont(
          Future.value(ByteData.sublistView(await File(font).readAsBytes())),
        );
      await loader.load();
      final icons = FontLoader('MaterialIcons')
        ..addFont(rootBundle.load('fonts/MaterialIcons-Regular.otf'));
      await icons.load();
    }
  });
  setUp(() {
    Get.testMode = true;
    final dio = ApiClient.instance.dio;
    dio.interceptors.clear();
    dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) {
          final data = options.path.contains('units_for_plan')
              ? {
                  'units': {
                    'AVAILABLE': [
                      {
                        'id': 1,
                        'code': 'BCC1-A-001',
                        'availability_status': 'AVAILABLE',
                      },
                    ],
                  },
                }
              : options.path.contains('_requests/77')
              ? {
                  'id': 77,
                  'signed_at': '2026-10-01',
                  'start_payment_date': '2026-11-01',
                  'unit_deposit_request': {
                    'customer_name': 'Contract Customer',
                    'customer_gender': 1,
                    'customer_phone_number': '012345678',
                    'deposit_amount': '50.00',
                  },
                  'unit': _unitJson,
                  'created_by': {
                    'name': 'Test Agent',
                    'phone_number': '012345678',
                  },
                  'created_at': '2026-10-01T12:00:00Z',
                  'hold_day': 1,
                  'release_date': '2026-10-02T12:00:00Z',
                  'status': 'APPROVED',
                  'customer_name': 'Customer One',
                  'customer_gender': 1,
                  'customer2_name': 'Customer Two',
                  'customer2_gender': 2,
                  'customer_phone_number': '012345678',
                  'customer_phone_number2': '098765432',
                  'deposit_amount': '50.00',
                  'receiving_amount': '0.00',
                  'remark': 'Test remark',
                }
              : options.path.endsWith('posts/1')
              ? {
                  'id': 1,
                  'title': 'Project news',
                  'content': '<p>Project announcement content.</p>',
                }
              : options.path.endsWith('units/1')
              ? _unitJson
              : options.path == 'api/units'
              ? [
                  _unitJson,
                  {
                    ..._unitJson,
                    'id': 2,
                    'code': 'BCC1-A-002',
                    'availability_status': 'UNAVAILABLE',
                  },
                ]
              : <dynamic>[];
          handler.resolve(
            Response(
              requestOptions: options,
              data: {'data': data},
              statusCode: 200,
            ),
          );
        },
      ),
    );
    Get.put<AuthController>(
      _Auth()
        ..currentUser.value = AppUser(
          id: 1,
          name: 'Sokha',
          phoneNumber: '012 345 678',
          roles: ['unit_controller'],
        ),
    );
    Get.put<HomeController>(
      _Home()
        ..projects.assignAll([
          Project(id: 1, name: 'Borey Chaktomuk City 1', shortCode: 'BCC1'),
          Project(id: 2, name: 'Borey Chaktomuk City 2', shortCode: 'BCC2'),
          Project(id: 3, name: 'East Land and Home', shortCode: 'ELH'),
        ]),
    );
    Get.put<DepositController>(_Deposits()..requests.assignAll([_deposit]));
    Get.put<ProjectDetailController>(
      _Project(
          Project(
            id: 1,
            name: 'Borey Chaktomuk City 1',
            unitTypes: [
              UnitType(id: 1, name: 'A long unit type name to check wrapping'),
            ],
          ),
        )
        ..units.assignAll([
          _unit,
          Unit(id: 2, code: 'BCC1-A-002', availabilityStatus: 'DEPOSIT'),
        ]),
    );
  });
  tearDown(() => Get.reset());

  test('PDF download exposes backend errors from byte responses', () async {
    final dio = ApiClient.instance.dio;
    dio.interceptors.clear();
    dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) {
          handler.reject(
            DioException(
              requestOptions: options,
              type: DioExceptionType.badResponse,
              response: Response(
                requestOptions: options,
                statusCode: 422,
                data: utf8.encode(
                  jsonEncode({
                    'error': {'code': 422, 'message': 'Contract access denied'},
                  }),
                ),
              ),
            ),
          );
        },
      ),
    );
    await expectLater(
      ContractRequestRepository().getContractPdfBytes(1),
      throwsA(
        isA<ApiException>().having(
          (e) => e.message,
          'message',
          'Contract access denied',
        ),
      ),
    );
  });

  for (final language in ['en', 'km']) {
    for (final width in [320.0, 390.0, 760.0]) {
      testWidgets('Screens fit $language at width $width with large text', (
        tester,
      ) async {
        tester.view.physicalSize = Size(width, 844);
        tester.view.devicePixelRatio = 1;
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);
        final screens = <Widget>[
          const LoginView(),
          const MainShell(),
          const ProjectDetailView(),
          const MoreView(),
          const DepositView(),
          const ProfileView(),
          const ForgotPasswordView(),
          HoldRequestFormView(unit: _unit),
          DepositRequestFormView(unit: _unit),
          ChangeUnitDepositView(depositRequest: _deposit),
          ContractRequestFormView(depositRequest: _deposit),
          const LoanCalculatorView(),
          const DocumentScannerView(),
          const SupportCenterView(),
          const ChangePasswordView(),
          const MembersView(),
          UnitDetailView(unit: _unit),
          UnitTypeDetailView(unitType: UnitType(id: 1, name: 'Townhouse')),
          FloorPlanView(
            project: Project(id: 1, name: 'Borey Chaktomuk City 1'),
          ),
          const UnitSearchView(),
          const PostDetailView(postId: 1),
        ];
        for (final screen in screens) {
          await tester.pumpWidget(_app(screen, language: language, scale: 1.3));
          await tester.pumpAndSettle();
          expect(
            tester.takeException(),
            isNull,
            reason: '${screen.runtimeType} at $width / $language',
          );
          // Scroll through lazy list children to check offscreen fields too.
          for (final list in find.byType(ListView).evaluate().toList()) {
            final finder = find.byWidget(list.widget);
            await tester.drag(finder, const Offset(0, -1400));
            await tester.pumpAndSettle();
            expect(
              tester.takeException(),
              isNull,
              reason: 'Scrolled ${screen.runtimeType} at $width / $language',
            );
          }
          await tester.pumpWidget(const SizedBox.shrink());
          await tester.pumpAndSettle();
        }
      });
    }
  }

  testWidgets('Four deposit steps preserve customer data with keyboard open', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 700);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    await tester.pumpWidget(
      _app(DepositRequestFormView(unit: _unit), keyboard: 260, scale: 1.3),
    );
    await tester.pumpAndSettle();
    Future<void> fill(String label, String value) async {
      final field = find.widgetWithText(TextFormField, label);
      await tester.ensureVisible(field);
      await tester.enterText(field, value);
      await tester.pumpAndSettle();
    }

    await fill('Surname and Name (Customer 1) *', 'Local UI Tester');
    final gender = find.byKey(const ValueKey('gender-0'));
    await tester.ensureVisible(gender);
    await tester.tap(gender);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Male').last);
    await tester.pumpAndSettle();
    await fill('Line 1 *', '012345678');
    await tester.tap(find.text('Next'));
    await tester.pumpAndSettle();
    expect(find.text('2 / 4'), findsOneWidget);
    await tester.tap(find.text('Next'));
    await tester.pumpAndSettle();
    await fill('Deposit amount (\$) *', '500');
    await tester.tap(find.text('Next'));
    await tester.pumpAndSettle();
    expect(find.text('4 / 4'), findsOneWidget);
    await tester.ensureVisible(find.byKey(const ValueKey('payment-0')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const ValueKey('payment-0')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Other').last);
    await tester.pumpAndSettle();
    expect(find.text('Installment Duration (months)'), findsOneWidget);
    await tester.tap(find.text('Back'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Back'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Back'));
    await tester.pumpAndSettle();
    final name = tester.widget<TextFormField>(
      find.widgetWithText(TextFormField, 'Surname and Name (Customer 1) *'),
    );
    expect(name.controller!.text, 'Local UI Tester');
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'Deposit action permissions survive redesign and search filters cards',
    (tester) async {
      await tester.pumpWidget(_app(const DepositView()));
      await tester.pumpAndSettle();
      expect(find.byTooltip('Cancel'), findsOneWidget);
      expect(find.byTooltip('Change unit / terms'), findsOneWidget);
      expect(find.text('Contract'), findsOneWidget);
      await tester.enterText(find.byType(TextField), 'not-a-unit');
      await tester.pump(const Duration(milliseconds: 400));
      await tester.pumpAndSettle();
      expect(find.byType(AppRequestCard), findsNothing);
      Get.find<AuthController>().currentUser.value = AppUser(
        id: 2,
        name: 'Other agent',
        roles: ['agent'],
      );
      await tester.pumpWidget(const SizedBox.shrink());
      await tester.pumpWidget(_app(const DepositView()));
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextField), 'BCC1');
      await tester.pump(const Duration(milliseconds: 400));
      await tester.pumpAndSettle();
      expect(find.byType(AppRequestCard), findsOneWidget);
      expect(find.byTooltip('Cancel'), findsNothing);
      expect(find.byTooltip('Change unit / terms'), findsNothing);
      expect(find.text('Contract'), findsNothing);
    },
  );

  for (final language in ['en', 'km']) {
    testWidgets(
      'All deposit steps and custom terms fit $language with large text and keyboard',
      (tester) async {
        tester.view.physicalSize = const Size(320, 700);
        tester.view.devicePixelRatio = 1;
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);
        await tester.pumpWidget(
          _app(
            DepositRequestFormView(unit: Unit.fromJson(_unitJson)),
            language: language,
            keyboard: 260,
            scale: 1.3,
          ),
        );
        await tester.pumpAndSettle();
        final l = AppLocalizations.of(
          tester.element(find.byType(DepositRequestFormView)),
        )!;
        Future<void> fill(String label, String value) async {
          final field = find.widgetWithText(TextFormField, label);
          await tester.ensureVisible(field);
          await tester.enterText(field, value);
          await tester.pumpAndSettle();
        }

        Future<void> next() async {
          await tester.tap(find.text(l.nextLabel));
          await tester.pumpAndSettle();
          expect(tester.takeException(), isNull);
        }

        await fill(l.customerOneNameLabel, 'Local UI Tester');
        await tester.ensureVisible(find.byKey(const ValueKey('gender-0')));
        await tester.tap(find.byKey(const ValueKey('gender-0')));
        await tester.pumpAndSettle();
        await tester.tap(find.text(l.male).last);
        await tester.pumpAndSettle();
        await fill(l.phoneLineOneLabel, '012345678');
        await next();
        await next();
        await fill('${l.depositAmount} (\$) *', '500');
        await next();
        await tester.ensureVisible(find.byKey(const ValueKey('payment-0')));
        await tester.pumpAndSettle();
        await tester.tap(find.byKey(const ValueKey('payment-0')));
        await tester.pumpAndSettle();
        await tester.tap(find.text(l.otherLabel).last);
        await tester.pumpAndSettle();
        final special = find.descendant(
          of: find.byType(SegmentedButton<bool>),
          matching: find.text(l.otherLabel),
        );
        await tester.ensureVisible(special);
        await tester.pumpAndSettle();
        await tester.tap(special);
        await tester.pumpAndSettle();
        final down = find.byType(DropdownButtonFormField<bool>);
        await tester.ensureVisible(down);
        await tester.pumpAndSettle();
        await tester.tap(down);
        await tester.pumpAndSettle();
        await tester.tap(find.text(l.yes).last);
        await tester.pumpAndSettle();
        await fill(l.downPaymentAmountLabel, '20000');
        await fill(l.installmentMonthsLabel, '120');
        await tester.ensureVisible(
          find.widgetWithText(TextFormField, l.remark),
        );
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);
      },
    );
  }

  for (final language in ['en', 'km']) {
    testWidgets(
      'Contract details load customer and dates at narrow width $language',
      (tester) async {
        tester.view.physicalSize = const Size(320, 844);
        tester.view.devicePixelRatio = 1;
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);
        await tester.pumpWidget(
          _app(
            const ContractRequestDetailView(id: 77),
            language: language,
            scale: 1.3,
          ),
        );
        await tester.pumpAndSettle();
        expect(find.text('BCC1-A-001'), findsOneWidget);
        await tester.scrollUntilVisible(find.text('Contract Customer'), 200);
        expect(find.text('Contract Customer'), findsOneWidget);
        await tester.scrollUntilVisible(find.text('Test remark'), 300);
        expect(find.text('Test remark'), findsOneWidget);
        expect(tester.takeException(), isNull);
      },
    );
  }

  for (final hold in [true, false]) {
    for (final language in ['en', 'km']) {
      testWidgets('Request detail loads fields hold=$hold language=$language', (
        tester,
      ) async {
        tester.view.physicalSize = const Size(320, 844);
        tester.view.devicePixelRatio = 1;
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);
        await tester.pumpWidget(
          _app(
            RequestDetailView(id: 77, hold: hold),
            language: language,
            scale: 1.3,
          ),
        );
        await tester.pumpAndSettle();
        expect(find.text('Test Agent'), findsOneWidget);
        if (!hold) {
          await tester.scrollUntilVisible(find.text('Customer One'), 200);
          expect(find.text('Customer One'), findsOneWidget);
          await tester.scrollUntilVisible(find.text('Customer Two'), 200);
          expect(find.text('Customer Two'), findsOneWidget);
        }
        await tester.scrollUntilVisible(find.text('Test remark'), 300);
        expect(find.text('Test remark'), findsOneWidget);
        expect(tester.takeException(), isNull);
      });
    }
  }

  testWidgets('Project rows swipe unit types horizontally and open units', (
    tester,
  ) async {
    Get.find<HomeController>().projects.assignAll([
      Project(
        id: 1,
        name: 'Project One',
        unitTypes: [
          UnitType(id: 1, name: 'L1'),
          UnitType(id: 2, name: 'L2'),
          UnitType(id: 3, name: 'L3'),
        ],
      ),
      Project(
        id: 2,
        name: 'Project Two',
        unitTypes: [UnitType(id: 4, name: 'Flat')],
      ),
    ]);
    await tester.pumpWidget(_app(const MainShell()));
    await tester.pumpAndSettle();
    expect(find.text('Project One'), findsOneWidget);
    expect(find.text('Project Two'), findsOneWidget);
    final row = find.byKey(const PageStorageKey('project-types-1'));
    expect(tester.widget<ListView>(row).scrollDirection, Axis.horizontal);
    await tester.drag(row, const Offset(-450, 0));
    await tester.pumpAndSettle();
    await tester.tap(find.text('L3'));
    await tester.pumpAndSettle();
    expect(find.text('BCC1-A-001'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('Five tabs animate and keep unit search when returning', (
    tester,
  ) async {
    await tester.pumpWidget(_app(const MainShell()));
    await tester.pumpAndSettle();
    expect(find.text('Units'), findsNWidgets(2));
    await tester.enterText(find.byType(TextField), 'BCC1');
    await tester.pump(const Duration(milliseconds: 400));
    await tester.pumpAndSettle();
    for (final label in ['Hold Req', 'Deposit Req', 'Contract Req', 'More']) {
      await tester.tap(find.byTooltip(label));
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
    }
    await tester.tap(find.byTooltip('Units'));
    await tester.pumpAndSettle();
    expect(
      tester.widget<TextField>(find.byType(TextField).first).controller!.text,
      'BCC1',
    );
  });

  testWidgets('Loan result layout preserves calculation', (tester) async {
    await tester.pumpWidget(_app(const LoanCalculatorView()));
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextField).at(0), '1200');
    await tester.tap(find.text('Calculate'));
    await tester.pumpAndSettle();
    expect(find.text('\$100.00'), findsOneWidget);
    expect(find.text('\$1,200.00'), findsOneWidget);
    expect(find.text('\$0.00'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('Capture redesigned screens at phone size', (tester) async {
    tester.view.physicalSize = const Size(390, 844);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    for (final entry in <String, Widget>{
      'home': const MainShell(),
      'login': const LoginView(),
      'units': const ProjectDetailView(),
      'requests': const DepositView(),
      'more': const MoreView(),
      'deposit-form': DepositRequestFormView(unit: Unit.fromJson(_unitJson)),
      'unit-detail': UnitDetailView(unit: Unit.fromJson(_unitJson)),
      'hold-form': HoldRequestFormView(unit: Unit.fromJson(_unitJson)),
    }.entries) {
      await tester.pumpWidget(_app(entry.value));
      await tester.pumpAndSettle();
      await _capture(tester, entry.key);
      if (entry.key == 'unit-detail') {
        await tester.drag(find.byType(ListView).first, const Offset(0, -500));
        await tester.pumpAndSettle();
        await _capture(tester, 'unit-information');
      }
      expect(tester.takeException(), isNull);
    }
    const preview = String.fromEnvironment('UI_PREVIEW_DIR');
    if (preview.isNotEmpty) {
      await tester.pumpWidget(
        _app(DepositRequestFormView(unit: Unit.fromJson(_unitJson))),
      );
      await tester.pumpAndSettle();
      Future<void> fill(String label, String value) async {
        final field = find.widgetWithText(TextFormField, label);
        await tester.ensureVisible(field);
        await tester.enterText(field, value);
        await tester.pumpAndSettle();
      }

      await fill('Surname and Name (Customer 1) *', 'Local UI Tester');
      await tester.ensureVisible(find.byKey(const ValueKey('gender-0')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('gender-0')));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Male').last);
      await tester.pumpAndSettle();
      await fill('Line 1 *', '012345678');
      await tester.tap(find.text('Next'));
      await tester.pumpAndSettle();
      await _capture(tester, 'deposit-step-2');
      await tester.tap(find.text('Next'));
      await tester.pumpAndSettle();
      await fill('Deposit amount (\$) *', '500');
      await _capture(tester, 'deposit-step-3');
      await tester.tap(find.text('Next'));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.byKey(const ValueKey('payment-0')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('payment-0')));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Other').last);
      await tester.pumpAndSettle();
      await _capture(tester, 'deposit-step-4');
    }
  });
}

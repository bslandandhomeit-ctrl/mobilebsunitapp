import 'dart:io';
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
              : options.path.endsWith('posts/1')
              ? {
                  'id': 1,
                  'title': 'Project news',
                  'content': '<p>Project announcement content.</p>',
                }
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

  testWidgets(
    'Expanded deposit fields fit and remain available with keyboard',
    (tester) async {
      tester.view.physicalSize = const Size(320, 700);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      await tester.pumpWidget(
        _app(DepositRequestFormView(unit: _unit), keyboard: 260, scale: 1.3),
      );
      await tester.pumpAndSettle();
      await tester.scrollUntilVisible(
        find.byType(CheckboxListTile),
        200,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.byType(CheckboxListTile));
      await tester.pumpAndSettle();
      await tester.tap(find.byType(CheckboxListTile));
      await tester.pumpAndSettle();
      await tester.scrollUntilVisible(
        find.text('Second customer name'),
        150,
        scrollable: find.byType(Scrollable).first,
      );
      expect(find.text('Second customer name'), findsOneWidget);
      await tester.scrollUntilVisible(
        find.byType(SwitchListTile),
        200,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.byType(SwitchListTile));
      await tester.pumpAndSettle();
      await tester.tap(find.byType(SwitchListTile));
      await tester.pumpAndSettle();
      await tester.scrollUntilVisible(
        find.text('First payment percentage (%)'),
        150,
        scrollable: find.byType(Scrollable).first,
      );
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'Deposit action permissions survive redesign and search filters cards',
    (tester) async {
      await tester.pumpWidget(_app(const DepositView()));
      await tester.pumpAndSettle();
      expect(find.byTooltip('Cancel'), findsOneWidget);
      expect(find.byTooltip('Change unit / terms'), findsOneWidget);
      expect(find.text('Contract'), findsOneWidget);
      await tester.enterText(find.byType(TextField), 'not-a-unit');
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
      await tester.pumpAndSettle();
      expect(find.byType(AppRequestCard), findsOneWidget);
      expect(find.byTooltip('Cancel'), findsNothing);
      expect(find.byTooltip('Change unit / terms'), findsNothing);
      expect(find.text('Contract'), findsNothing);
    },
  );

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
      'deposit-form': DepositRequestFormView(unit: _unit),
    }.entries) {
      await tester.pumpWidget(_app(entry.value));
      await tester.pumpAndSettle();
      await _capture(tester, entry.key);
      expect(tester.takeException(), isNull);
    }
  });
}

import 'package:bs_contract_flutter/features/auth/models/user.dart';
import 'package:bs_contract_flutter/features/auth/services/customer_phone.dart';
import 'package:bs_contract_flutter/features/auth/services/phone_auth_error.dart';
import 'package:bs_contract_flutter/features/auth/views/register_view.dart';
import 'package:bs_contract_flutter/l10n/app_localizations.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:bs_contract_flutter/app/views/main_shell.dart';
import 'package:bs_contract_flutter/features/auth/controllers/auth_controller.dart';
import 'package:bs_contract_flutter/features/home/controllers/home_controller.dart';
import 'package:bs_contract_flutter/features/projects/models/project.dart';

class _CustomerAuth extends AuthController {
  @override
  // Avoid production initialization and network calls in this test.
  // ignore: must_call_super
  void onInit() {}
}

class _CustomerHome extends HomeController {
  @override
  // Avoid production initialization and network calls in this test.
  // ignore: must_call_super
  void onInit() {}
}

void main() {
  test(
    'phone auth errors identify configuration, invalid codes and limits',
    () {
      expect(
        phoneAuthError('internal-error', 'An internal error has occurred.'),
        contains('contact support'),
      );
      expect(
        phoneAuthError('invalid-verification-code', null),
        contains('incorrect'),
      );
      expect(
        phoneAuthError('session-expired', null),
        contains('Request a new code'),
      );
      expect(phoneAuthError('too-many-requests', null), contains('Wait'));
      expect(
        phoneAuthError('unknown', 'Service unavailable'),
        'Service unavailable (unknown)',
      );
    },
  );
  tearDown(() => Get.reset());

  testWidgets('customer shell shows home projects without staff tabs', (
    tester,
  ) async {
    Get.testMode = true;
    Get.put<AuthController>(
      _CustomerAuth()
        ..currentUser.value = AppUser(
          id: 1,
          name: 'Customer',
          roles: ['customer'],
        ),
    );
    Get.put<HomeController>(
      _CustomerHome()..projects.add(Project(id: 1, name: 'Customer Project')),
    );
    await tester.pumpWidget(
      const GetMaterialApp(
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: AppLocalizations.supportedLocales,
        home: MainShell(),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('Customer Project'), findsOneWidget);
    expect(find.byIcon(Icons.logout_rounded), findsOneWidget);
    expect(find.byIcon(Icons.lock_clock_outlined), findsNothing);
    expect(find.byIcon(Icons.payments_outlined), findsNothing);
    expect(find.byIcon(Icons.settings_rounded), findsNothing);
  });
  test('normalizes local and international Cambodian phone numbers', () {
    expect(customerPhone('012 345 678'), '012345678');
    expect(customerPhone('+855 (12) 345-678'), '012345678');
    expect(firebaseCustomerPhone('012345678'), '+85512345678');
    for (final phone in [
      '12345',
      '+15551234567',
      '+855012345678',
      '000000000',
      'phone',
    ]) {
      expect(customerPhone(phone), isNull);
    }
  });

  test('customer, mixed and unknown roles cannot access staff features', () {
    for (final roles in <List<String>>[
      [],
      ['customer'],
      ['unknown'],
      ['customer', 'unit_controller'],
    ]) {
      final user = AppUser(id: 1, name: 'Customer', roles: roles);
      expect(user.canAccessStaffFeatures, isFalse);
      expect(user.canApproveHold, isFalse);
      expect(user.canApproveDeposit, isFalse);
      expect(user.canCancelDeposit, isFalse);
    }
    expect(
      AppUser(id: 2, name: 'Staff', roles: ['agent']).canAccessStaffFeatures,
      isTrue,
    );
  });

  testWidgets(
    'registration validates missing details before Firebase is called',
    (tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          localizationsDelegates: AppLocalizations.localizationsDelegates,
          supportedLocales: AppLocalizations.supportedLocales,
          home: RegisterView(),
        ),
      );
      await tester.ensureVisible(find.text('Register'));
      await tester.tap(find.text('Register'));
      await tester.pumpAndSettle();
      expect(find.text('Enter your first name.'), findsOneWidget);
      expect(find.text('Enter your last name.'), findsOneWidget);
      expect(
        find.text('Enter a valid Cambodian phone number.'),
        findsOneWidget,
      );
      expect(find.text('Use 8–32 characters.'), findsOneWidget);
      expect(find.text('Sale Team'), findsNothing);
    },
  );
}

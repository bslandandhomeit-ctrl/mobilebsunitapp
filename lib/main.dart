import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_crashlytics/firebase_crashlytics.dart';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:get/get.dart';
import 'package:onesignal_flutter/onesignal_flutter.dart';

import 'app/bindings/initial_binding.dart';
import 'app/routes/app_pages.dart';
import 'app/theme/app_theme.dart';
import 'core/constants/api_constants.dart';
import 'core/network/tls_intermediates.dart';
import 'core/storage/local_storage.dart';
import 'firebase_options.dart';
import 'l10n/app_localizations.dart';

// From build.gradle in the original bs-contract-android project.
const _oneSignalAppId = '25dd12b1-8fda-4085-8ba7-4bea5c12dfb3';

// Plain entry point (e.g. tests, or `flutter run` with no flavor): defaults to
// production. For day-to-day work use a flavor instead — see RUN.md:
//   UAT:        flutter run -t lib/main_uat.dart
//   Production: flutter run -t lib/main_prod.dart
// On Android, also pass --flavor uat or --flavor prod respectively.
Future<void> main() async {
  await startBsUnits();
}

/// Shared app bootstrap. The flavor entry points (lib/main_prod.dart /
/// lib/main_uat.dart) pick the backend with ApiConstants.useProduction()/useUat()
/// first, then call this.
Future<void> startBsUnits() async {
  WidgetsFlutterBinding.ensureInitialized();
  trustServerIntermediates();
  await LocalStorage.init();

  // Both Android (google-services.json) and iOS are registered in the
  // "contract-register" Firebase project (via `flutterfire configure`).
  if (ApiConstants.isProduction) {
    await Firebase.initializeApp(
      options: DefaultFirebaseOptions.currentPlatform,
    );
    FlutterError.onError = FirebaseCrashlytics.instance.recordFlutterFatalError;
    OneSignal.initialize(_oneSignalAppId);
    OneSignal.Notifications.requestPermission(true);
  }

  runApp(const BsUnitsApp());
}

class BsUnitsApp extends StatelessWidget {
  const BsUnitsApp({super.key});

  @override
  Widget build(BuildContext context) {
    return GetMaterialApp(
      title: 'BS Units',
      builder: (context, child) => ApiConstants.isProduction
          ? child!
          : Banner(
              message: ApiConstants.environmentLabel,
              location: BannerLocation.topEnd,
              color: ApiConstants.environmentLabel == 'UAT'
                  ? Colors.blue.shade700
                  : Colors.orange.shade800,
              child: child!,
            ),
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light,
      // Bilingual km/en, matching the original app — falls back to the
      // device locale, defaulting to English if neither matches.
      localizationsDelegates: const [
        AppLocalizations.delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      supportedLocales: AppLocalizations.supportedLocales,
      initialBinding: InitialBinding(),
      initialRoute: AppPages.initial,
      getPages: AppPages.routes,
    );
  }
}

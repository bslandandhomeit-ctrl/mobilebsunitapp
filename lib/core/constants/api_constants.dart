import 'package:flutter/foundation.dart' show kReleaseMode;

/// API configuration for the BS Land & Home / BS Units backend.
///
/// [baseUrl] is the real production URL found in the original
/// bs-contract-android source (`ApiHelper.BASE_URL`) — confirmed live
/// (2026-09-11). The Postman collection's "test" environment
/// (`masterplan.hiayongkuy.com`) is stale/dead (~2020) and no longer used.
///
/// [oauthClientId]/[oauthClientSecret] are the real production values
/// hardcoded in the original app (`ApiHelper.CLIENT_SECRET` /
/// `LoginActivity.java`) — confirmed to pass client authentication against
/// production (2026-09-11; the stale Postman "test" secret returned
/// `invalid_client` here, this one does not).
///
/// KNOWN SERVER ISSUE (2026-10-05): `contract.chaktomukcity.com`'s TLS cert
/// chain supplies unrelated Sectigo certificates instead of the
/// required Let's Encrypt intermediates. Confirmed independent of this app
/// (`openssl s_client -connect contract.chaktomukcity.com:443` fails the
/// same way from a plain host machine). Until the server is reconfigured to
/// serve the full chain, this app registers the missing issuers with
/// `trustServerIntermediates()` while keeping certificate validation enabled.
class ApiConstants {
  ApiConstants._();

  // Production. Its incomplete TLS chain (above) is worked around by
  // `trustServerIntermediates()` in core/network/tls_intermediates.dart.
  // For the local dev server instead, use 'http://10.0.2.2:8000/' (the
  // Android emulator's alias for the host's localhost).
  static const String productionBaseUrl = 'https://contract.chaktomukcity.com/';
  static const String uatHost = '10.3.0.99';
  static const String uatBaseUrl = 'http://$uatHost:8082/';
  static const String developmentBaseUrl = 'http://127.0.0.1:8000/';
  static String? _imageBaseUrl;

  /// Development reads uploaded images from UAT while keeping API calls local.
  static String imageUrl(String value) {
    if (_imageBaseUrl == null || value.isEmpty) return value;
    final image = Uri.tryParse(value);
    if (image == null) return value;
    final source = Uri.parse(baseUrl);
    final target = Uri.parse(_imageBaseUrl!);
    if (!image.hasAuthority) {
      if (image.hasScheme) return value;
      return target.resolveUri(image).toString();
    }
    if (!['http', 'https', ''].contains(image.scheme)) return value;
    if (![
      source.host,
      'localhost',
      '127.0.0.1',
      '0.0.0.0',
      '10.0.2.2',
      uatHost,
    ].contains(image.host)) {
      return value;
    }
    return image
        .replace(
          scheme: target.scheme,
          host: target.host,
          port: target.port,
        )
        .toString();
  }

  /// Local API server; the server can independently use the database on .99.
  /// Android over USB needs `adb reverse tcp:8000 tcp:8000` for localhost.
  static void useDevelopment() {
    baseUrl = const String.fromEnvironment(
      'API_BASE_URL',
      defaultValue: developmentBaseUrl,
    );
    _envLabel = 'DEVELOPMENT';
    _imageBaseUrl = const String.fromEnvironment(
      'IMAGE_BASE_URL',
      defaultValue: uatBaseUrl,
    );
  }

  // Which backend the app talks to. The flavors set this at startup via
  // useProduction() / useUat() (lib/main_prod.dart / lib/main_uat.dart).
  // Defaults to production; a build-time --dart-define=API_BASE_URL=... still
  // overrides it for a one-off build.
  static String baseUrl = const String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: productionBaseUrl,
  );

  static bool get isProduction => baseUrl == productionBaseUrl;

  // Corner banner on non-production builds: APP_ENV_LABEL if given, otherwise
  // "UAT" for the UAT server and "LOCAL TEST" for anything else.
  static String _envLabel = const String.fromEnvironment('APP_ENV_LABEL');

  static String get environmentLabel {
    if (_envLabel.isNotEmpty) return _envLabel;
    return Uri.parse(baseUrl).host == uatHost ? 'UAT' : 'LOCAL TEST';
  }

  /// Point the app at the production backend (the real live server).
  /// Called by lib/main_prod.dart (the `prod` flavor).
  static void useProduction() {
    _imageBaseUrl = null;
    baseUrl = productionBaseUrl;
    _envLabel = '';
  }

  /// Point the app at the UAT backend (10.3.0.99 — needs the FortiClient VPN).
  /// Called by lib/main_uat.dart (the `uat` flavor).
  static void useUat() {
    _imageBaseUrl = null;
    baseUrl = uatBaseUrl;
    // Same UAT backend either way, but label it by build: the released APK
    // given to testers shows "UAT"; a developer running `flutter run` (debug)
    // shows "DEV" so a local run is never mistaken for the shared UAT build.
    _envLabel = kReleaseMode ? 'UAT' : 'DEV';
  }

  static const String oauthClientId = String.fromEnvironment(
    'OAUTH_CLIENT_ID',
    defaultValue: '2',
  );
  static const String oauthClientSecret = String.fromEnvironment(
    'OAUTH_CLIENT_SECRET',
    defaultValue: 'Y4ond2PoAgrmNiU3ZhxJmQ2tk44ZBj44S3VLeaRn',
  );

  static const String oauthTokenPath = 'oauth/token';
  static const String whoamiPath = 'api/whoami';
  static const String homePath = 'api/whoami/home';
  static const String bannersPath = 'api/banners';
  static const String saleRepresentativesPath = 'api/sale_representatives';
  static const String projectsPath = 'api/projects';
  static const String unitTypesPath = 'api/unit_types';
  static const String unitsPath = 'api/units';
  static const String paymentOptionsPath = 'api/payment_options';
  static const String unitHoldRequestsPath = 'api/unit_hold_requests';
  static const String unitDepositRequestsPath = 'api/unit_deposit_requests';
  static const String unitContractRequestsPath = 'api/unit_contract_requests';
  static const String notificationsPath = 'api/notifications';
  static const String postsPath = 'api/posts';
  static const String changePasswordPath = 'api/whoami/change_password';
  static const String membersPath = 'api/whoami/members';
  static const String forgetPasswordPath = 'api/users/forgetPassword';
  static const String generateResetTokenPath =
      'api/users/generateResetPasswordToken';
  static const String resetPasswordPath = 'api/users/resetPassword';
  static const String appVersionPath = 'api/app_version';
  static const String onesignalPlayerIdPath = 'api/whoami/onesignal_player_id';
  static String contractPdfPath(int contractId) =>
      'api/contract/$contractId/pdf';
}

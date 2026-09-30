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
/// KNOWN SERVER ISSUE (2026-09-11): `contract.chaktomukcity.com`'s TLS cert
/// chain is incomplete — it serves only its leaf certificate, not the
/// required Let's Encrypt intermediate. Confirmed independent of this app
/// (`openssl s_client -connect contract.chaktomukcity.com:443` fails the
/// same way from a plain host machine). Until the server is reconfigured to
/// serve the full chain, requests from this app (and likely many strict
/// TLS clients) will fail with a certificate verification error. Not
/// something to fix here — flag it to whoever manages that server.
class ApiConstants {
  ApiConstants._();

  // Production. Its incomplete TLS chain (above) is worked around by
  // `trustServerIntermediates()` in core/network/tls_intermediates.dart.
  // For the local dev server instead, use 'http://10.0.2.2:8000/' (the
  // Android emulator's alias for the host's localhost).
  static const String baseUrl = 'https://contract.chaktomukcity.com/';

  static const String oauthClientId = '2';
  static const String oauthClientSecret = 'Y4ond2PoAgrmNiU3ZhxJmQ2tk44ZBj44S3VLeaRn';

  static const String oauthTokenPath = 'oauth/token';
  static const String whoamiPath = 'api/whoami';
  static const String homePath = 'api/whoami/home';
  static const String bannersPath = 'api/banners';
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
  static const String generateResetTokenPath = 'api/users/generateResetPasswordToken';
  static const String resetPasswordPath = 'api/users/resetPassword';
  static const String appVersionPath = 'api/app_version';
  static const String onesignalPlayerIdPath = 'api/whoami/onesignal_player_id';
  static String contractPdfPath(int contractId) => 'api/contract/$contractId/pdf';
}

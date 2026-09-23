import 'package:get_storage/get_storage.dart';

/// Thin wrapper around [GetStorage] for auth/session persistence.
class LocalStorage {
  LocalStorage._();
  static final GetStorage _box = GetStorage();

  static const _keyAccessToken = 'access_token';
  static const _keyRefreshToken = 'refresh_token';

  static Future<void> init() => GetStorage.init();

  static String? get accessToken => _box.read<String>(_keyAccessToken);
  static String? get refreshToken => _box.read<String>(_keyRefreshToken);

  static bool get isLoggedIn => accessToken != null;

  static Future<void> saveTokens({
    required String accessToken,
    String? refreshToken,
  }) async {
    await _box.write(_keyAccessToken, accessToken);
    if (refreshToken != null) {
      await _box.write(_keyRefreshToken, refreshToken);
    }
  }

  static Future<void> clearAuth() async {
    await _box.remove(_keyAccessToken);
    await _box.remove(_keyRefreshToken);
  }
}

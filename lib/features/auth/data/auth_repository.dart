import 'package:dio/dio.dart';

import '../../../core/constants/api_constants.dart';
import '../../../core/models/paginated_response.dart';
import '../../../core/network/api_client.dart';
import '../../../core/storage/local_storage.dart';
import '../models/auth_token.dart';
import '../models/user.dart';

/// Talks to the Laravel Passport oauth/token + whoami endpoints.
class AuthRepository {
  final ApiClient _client = ApiClient.instance;

  Future<AuthToken> login({
    required String username,
    required String password,
  }) async {
    final response = await _client.post<Map<String, dynamic>>(
      ApiConstants.oauthTokenPath,
      data: FormData.fromMap({
        'grant_type': 'password',
        'client_id': ApiConstants.oauthClientId,
        'client_secret': ApiConstants.oauthClientSecret,
        'username': username,
        'password': password,
        'scope': '*',
      }),
    );

    final token = AuthToken.fromJson(response.data!);
    await LocalStorage.saveTokens(
      accessToken: token.accessToken,
      refreshToken: token.refreshToken,
    );
    return token;
  }

  Future<AppUser> getAuthenticatedUser() async {
    final response = await _client.get<Map<String, dynamic>>(
      ApiConstants.whoamiPath,
      queryParameters: {'embed': 'identifications,roles,manager'},
    );
    return AppUser.fromJson(PaginatedResponse.unwrap(response.data));
  }

  /// Verified against `Api/UserController@update`: only email/gender/
  /// birthdate/avatar are editable here (name/phone are not).
  Future<AppUser> updateProfile({
    String? email,
    String? gender,
    DateTime? birthdate,
    String? avatarPath,
  }) async {
    final formData = FormData.fromMap({
      'email': ?email,
      'gender': ?gender,
      'birthdate': birthdate == null
          ? null
          : '${birthdate.year.toString().padLeft(4, '0')}-'
              '${birthdate.month.toString().padLeft(2, '0')}-'
              '${birthdate.day.toString().padLeft(2, '0')}',
      if (avatarPath != null) 'avatar': await MultipartFile.fromFile(avatarPath),
    });
    final response = await _client.put<Map<String, dynamic>>(
      ApiConstants.whoamiPath,
      data: formData,
    );
    return AppUser.fromJson(PaginatedResponse.unwrap(response.data));
  }

  /// Verified against `Api/UserController@changePassword`: new password
  /// must differ from the current one and be at least 6 characters.
  Future<void> changePassword({
    required String currentPassword,
    required String newPassword,
  }) {
    return _client.post(
      ApiConstants.changePasswordPath,
      data: {
        'password_current': currentPassword,
        'password_new': newPassword,
      },
    );
  }

  /// Verified against `Api/OnesignalController@updateAuthUserPlayerId`:
  /// registers this device's OneSignal subscription id against the
  /// current Passport *token* (not the user) — required for push
  /// notifications to actually reach this device/session.
  Future<void> updateOnesignalPlayerId(String playerId) => _client.put(
    ApiConstants.onesignalPlayerIdPath,
    data: {'player_id': playerId},
  );

  Future<PaginatedResponse<AppUser>> getMembers() async {
    final response = await _client.get<Map<String, dynamic>>(ApiConstants.membersPath);
    return PaginatedResponse.fromJson(response.data!, AppUser.fromJson);
  }

  Future<void> logout() async {
    try {
      await _client.post('${ApiConstants.whoamiPath}/logout');
    } finally {
      await LocalStorage.clearAuth();
    }
  }
}

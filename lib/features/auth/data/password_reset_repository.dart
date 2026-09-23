import '../../../core/constants/api_constants.dart';
import '../../../core/network/api_client.dart';

/// Verified against `Api/Password/ForgetPasswordController` +
/// `ResetPasswordController`: a 3-step SMS-based flow — request a code,
/// exchange the code for a one-time reset token, then reset with that
/// token.
class PasswordResetRepository {
  final ApiClient _client = ApiClient.instance;

  Future<void> sendSmsCode(String phoneNumber) => _client.post(
    ApiConstants.forgetPasswordPath,
    data: {'phone_number': phoneNumber},
  );

  Future<String> verifyCode({
    required String phoneNumber,
    required String verificationCode,
  }) async {
    final response = await _client.post<Map<String, dynamic>>(
      ApiConstants.generateResetTokenPath,
      data: {'phone_number': phoneNumber, 'verification_code': verificationCode},
    );
    final data = response.data!;
    final token = (data['data'] is Map ? data['data']['token'] : data['token'])?.toString();
    return token ?? '';
  }

  Future<void> resetPassword({required String token, required String password}) => _client.post(
    ApiConstants.resetPasswordPath,
    data: {'token': token, 'password': password},
  );
}

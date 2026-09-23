class AuthToken {
  AuthToken({required this.accessToken, this.refreshToken, this.expiresIn});

  factory AuthToken.fromJson(Map<String, dynamic> json) => AuthToken(
    accessToken: json['access_token'] as String,
    refreshToken: json['refresh_token'] as String?,
    expiresIn: json['expires_in'] as int?,
  );

  final String accessToken;
  final String? refreshToken;
  final int? expiresIn;
}

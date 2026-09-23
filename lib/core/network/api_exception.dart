/// Normalized error thrown by [ApiClient] so features never have to deal
/// with raw [DioException]s.
class ApiException implements Exception {
  ApiException({required this.message, this.statusCode, this.errors});

  final String message;
  final int? statusCode;
  final Map<String, dynamic>? errors;

  @override
  String toString() => message;
}

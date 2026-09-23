import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';

import '../constants/api_constants.dart';
import '../storage/local_storage.dart';
import 'api_exception.dart';

/// Shared Dio client: attaches the bearer token to every request (except
/// the oauth/token call itself) and normalizes errors to [ApiException].
class ApiClient {
  ApiClient._internal() {
    _dio = Dio(
      BaseOptions(
        baseUrl: ApiConstants.baseUrl,
        headers: {'Accept': 'application/json'},
      ),
    );

    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) {
          final token = LocalStorage.accessToken;
          if (token != null && !options.path.contains(ApiConstants.oauthTokenPath)) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          handler.next(options);
        },
        onError: (error, handler) {
          handler.next(error);
        },
      ),
    );

    if (kDebugMode) {
      _dio.interceptors.add(
        LogInterceptor(requestBody: true, responseBody: true),
      );
    }
  }

  static final ApiClient instance = ApiClient._internal();
  late final Dio _dio;

  Dio get dio => _dio;

  Future<Response<T>> get<T>(
    String path, {
    Map<String, dynamic>? queryParameters,
  }) => _guard(() => _dio.get<T>(path, queryParameters: queryParameters));

  Future<Response<T>> post<T>(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
  }) => _guard(
    () => _dio.post<T>(path, data: data, queryParameters: queryParameters),
  );

  Future<Response<T>> put<T>(String path, {dynamic data}) =>
      _guard(() => _dio.put<T>(path, data: data));

  Future<Response<T>> delete<T>(String path, {dynamic data}) =>
      _guard(() => _dio.delete<T>(path, data: data));

  Future<Response<T>> _guard<T>(Future<Response<T>> Function() request) async {
    try {
      return await request();
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  /// Verified against the backend source (`Controller::sendErrorJsonResponse`,
  /// 2026-09-11): virtually every non-oauth error response is
  /// `{"error": {"code": ..., "message": "..."}}` — an earlier version of
  /// this parser only checked a top-level `message`/`error_description`,
  /// so real backend error text (validation failures, permission errors,
  /// etc.) was silently swallowed in favor of a generic fallback for every
  /// single error case. Confirmed on-device: a real 403 came back as
  /// `{"error":{"code":403,"message":"You don't have permission..."}}` and
  /// the UI showed "Something went wrong" instead.
  ///
  /// oauth/token errors are a different shape (League OAuth2 Server, not
  /// this app's Controller): `{"error": "invalid_credentials",
  /// "error_description": "..."}` — `error` is a string there, not a map.
  ApiException toApiException(DioException e) {
    final response = e.response;
    final data = response?.data;
    String message = 'Something went wrong. Please try again.';
    Map<String, dynamic>? errors;

    if (data is Map<String, dynamic>) {
      final error = data['error'];
      if (error is Map<String, dynamic> && error['message'] != null) {
        message = error['message'].toString();
      } else {
        message = (data['message'] ?? data['error_description'] ?? message)
            .toString();
      }
      if (data['errors'] is Map<String, dynamic>) {
        errors = data['errors'] as Map<String, dynamic>;
      }
    } else if (e.type == DioExceptionType.connectionTimeout ||
        e.type == DioExceptionType.receiveTimeout ||
        e.type == DioExceptionType.connectionError) {
      message = 'Cannot reach the server. Check your connection.';
    }

    return ApiException(
      message: message,
      statusCode: response?.statusCode,
      errors: errors,
    );
  }
}

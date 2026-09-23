import 'package:bs_contract_flutter/core/network/api_client.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

DioException _errorWithResponse(dynamic data, {int statusCode = 400}) {
  final requestOptions = RequestOptions(path: '/test');
  return DioException(
    requestOptions: requestOptions,
    response: Response(
      requestOptions: requestOptions,
      data: data,
      statusCode: statusCode,
    ),
    type: DioExceptionType.badResponse,
  );
}

void main() {
  group('ApiClient.toApiException', () {
    final client = ApiClient.instance;

    test(
      'reads the real message from the backend\'s {"error":{"message":...}} envelope',
      () {
        // Regression guard: this is the actual shape returned by
        // Controller::sendErrorJsonResponse for every non-oauth error. An
        // earlier version of this parser only checked a top-level
        // `message`/`error_description` and silently fell back to a
        // generic "Something went wrong" for every real backend error.
        final exception = client.toApiException(
          _errorWithResponse({
            'error': {
              'code': 403,
              'message': "You don't have permission to process your request.",
            },
          }, statusCode: 403),
        );

        expect(exception.message, "You don't have permission to process your request.");
        expect(exception.statusCode, 403);
      },
    );

    test('falls back to a top-level message for the oauth/token error shape', () {
      // League OAuth2 Server errors are a different shape: `error` is a
      // string, not a map, so the {"error":{"message":...}} branch must
      // not fire here.
      final exception = client.toApiException(
        _errorWithResponse({
          'error': 'invalid_credentials',
          'error_description': 'The user credentials were incorrect.',
        }, statusCode: 401),
      );

      expect(exception.message, 'The user credentials were incorrect.');
    });

    test('captures field-level validation errors', () {
      final exception = client.toApiException(
        _errorWithResponse({
          'error': {'code': 422, 'message': 'The given data was invalid.'},
          'errors': {
            'phone_number': ['The phone number field is required.'],
          },
        }, statusCode: 422),
      );

      expect(exception.errors, isNotNull);
      expect(exception.errors!['phone_number'], isA<List>());
    });

    test('uses a connectivity-specific message for connection errors', () {
      final requestOptions = RequestOptions(path: '/test');
      final exception = client.toApiException(
        DioException(
          requestOptions: requestOptions,
          type: DioExceptionType.connectionError,
        ),
      );

      expect(exception.message, contains('Cannot reach the server'));
    });

    test('falls back to a generic message when the response has no known shape', () {
      final exception = client.toApiException(_errorWithResponse('unexpected string body'));
      expect(exception.message, 'Something went wrong. Please try again.');
    });
  });
}

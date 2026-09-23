import 'package:bs_contract_flutter/core/models/paginated_response.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('PaginatedResponse.unwrap', () {
    test('extracts the nested data map for a single-resource envelope', () {
      final result = PaginatedResponse.unwrap({
        'data': {'id': 1, 'name': 'Alice'},
      });
      expect(result, {'id': 1, 'name': 'Alice'});
    });

    test('falls back to the raw map when there is no data key', () {
      final result = PaginatedResponse.unwrap({'id': 1});
      expect(result, {'id': 1});
    });

    test('returns an empty map for null input', () {
      expect(PaginatedResponse.unwrap(null), <String, dynamic>{});
    });
  });

  group('PaginatedResponse.unwrapList', () {
    test('extracts a nested data list', () {
      final result = PaginatedResponse.unwrapList({
        'data': [
          {'id': 1},
          {'id': 2},
        ],
      });
      expect(result, hasLength(2));
    });

    test('accepts a bare top-level list', () {
      final result = PaginatedResponse.unwrapList([
        {'id': 1},
      ]);
      expect(result, hasLength(1));
    });

    test('returns an empty list when there is no list anywhere', () {
      expect(PaginatedResponse.unwrapList({'id': 1}), isEmpty);
    });
  });

  group('PaginatedResponse.fromJson', () {
    test('parses items and pagination metadata from a Laravel-style page', () {
      final response = PaginatedResponse<int>.fromJson({
        'data': [
          {'id': 1},
          {'id': 2},
        ],
        'meta': {'current_page': 1, 'last_page': 3, 'total': 6},
      }, (json) => json['id'] as int);

      expect(response.items, [1, 2]);
      expect(response.currentPage, 1);
      expect(response.lastPage, 3);
      expect(response.total, 6);
    });

    test('reads pagination fields from the top level when there is no meta key', () {
      final response = PaginatedResponse<int>.fromJson({
        'data': [
          {'id': 1},
        ],
        'current_page': 2,
      }, (json) => json['id'] as int);

      expect(response.items, [1]);
      expect(response.currentPage, 2);
    });
  });
}

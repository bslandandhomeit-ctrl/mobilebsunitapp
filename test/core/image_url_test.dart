import 'package:bs_contract_flutter/core/constants/api_constants.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  tearDown(ApiConstants.useProduction);
  test('development images use UAT while API stays local', () {
    ApiConstants.useDevelopment();
    expect(ApiConstants.baseUrl, 'http://127.0.0.1:8000/');
    expect(
      ApiConstants.imageUrl('http://localhost:8000/storage/photo.jpg?v=2'),
      'http://10.3.0.99:8082/storage/photo.jpg?v=2',
    );
    expect(
      ApiConstants.imageUrl('/storage/photo.jpg'),
      'http://10.3.0.99:8082/storage/photo.jpg',
    );
    expect(
      ApiConstants.imageUrl('https://cdn.example.com/photo.jpg'),
      'https://cdn.example.com/photo.jpg',
    );
  });
  test('UAT and production keep supplied image URLs', () {
    const url = 'https://example.com/photo.jpg';
    ApiConstants.useUat();
    expect(ApiConstants.imageUrl(url), url);
    ApiConstants.useProduction();
    expect(ApiConstants.imageUrl(url), url);
  });
}

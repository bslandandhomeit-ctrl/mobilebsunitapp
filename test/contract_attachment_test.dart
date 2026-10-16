import 'dart:typed_data';

import 'package:flutter_test/flutter_test.dart';
import 'package:bs_contract_flutter/features/contract_request/data/contract_attachment.dart';
import 'package:bs_contract_flutter/core/network/api_exception.dart';

void main() {
  test(
    'uploads converted photo bytes with JPEG filename and MIME type',
    () async {
      final bytes = Uint8List.fromList([0xff, 0xd8, 0xff, 0xe0, 0xff, 0xd9]);
      final upload = await prepareContractAttachment(
        'photo.heic',
        0,
        encoder: (_) async => bytes,
      );
      expect(upload.filename, 'attachment_1.jpg');
      expect(upload.contentType.toString(), 'image/jpeg');
      expect(await upload.finalize().expand((chunk) => chunk).toList(), bytes);
    },
  );

  test('rejects unreadable or unconverted images before upload', () async {
    for (final bytes in [
      null,
      Uint8List.fromList([1, 2, 3, 4]),
    ]) {
      await expectLater(
        prepareContractAttachment('photo', 1, encoder: (_) async => bytes),
        throwsA(isA<ApiException>()),
      );
    }
  });
}

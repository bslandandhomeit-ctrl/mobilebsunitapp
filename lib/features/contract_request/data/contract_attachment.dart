import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_image_compress/flutter_image_compress.dart';

import '../../../core/network/api_exception.dart';

typedef AttachmentEncoder = Future<Uint8List?> Function(String path);

Future<Uint8List?> _encodeJpeg(String path) =>
    FlutterImageCompress.compressWithFile(
      path,
      // Short side about 1600px keeps documents readable and each photo well
      // under 1 MB, so it also fits servers still on PHP's 2 MB upload default.
      minWidth: 1600,
      minHeight: 1600,
      quality: 80,
      format: CompressFormat.jpeg,
    );

/// Normalize camera/gallery formats for the Laravel image validator.
Future<MultipartFile> prepareContractAttachment(
  String path,
  int index, {
  AttachmentEncoder? encoder,
}) async {
  try {
    final bytes = await (encoder ?? _encodeJpeg)(path);
    if (bytes == null ||
        bytes.length < 4 ||
        bytes[0] != 0xff ||
        bytes[1] != 0xd8 ||
        bytes[2] != 0xff) {
      throw const FormatException('Image conversion failed');
    }
    return MultipartFile.fromBytes(
      bytes,
      filename: 'attachment_${index + 1}.jpg',
      contentType: DioMediaType('image', 'jpeg'),
    );
  } catch (_) {
    throw ApiException(
      message:
          'Could not read attachment ${index + 1}. Please select another photo.',
    );
  }
}

import 'package:dio/dio.dart';

import '../../../core/constants/api_constants.dart';
import '../../../core/models/paginated_response.dart';
import '../../../core/network/api_client.dart';
import '../models/unit_comment.dart';

class UnitCommentRepository {
  final ApiClient _client = ApiClient.instance;

  Future<PaginatedResponse<UnitComment>> getComments(int unitId) async {
    final response = await _client.get<Map<String, dynamic>>(
      '${ApiConstants.unitsPath}/$unitId/comments',
    );
    return PaginatedResponse.fromJson(response.data!, UnitComment.fromJson);
  }

  /// [mediaPaths]: optional photo attachments. Verified against
  /// `Api/UnitController@storeComment` (2026-09-11): field name is `media`
  /// (an array — Spatie Media Library on the backend), each file must be
  /// jpg/jpeg/png/gif/webp/bmp and ≤2048KB.
  Future<void> addComment(int unitId, String content, {List<String> mediaPaths = const []}) async {
    final data = mediaPaths.isEmpty
        ? {'content': content}
        : FormData.fromMap({
            'content': content,
            'media[]': [
              for (final path in mediaPaths) await MultipartFile.fromFile(path),
            ],
          });
    await _client.post('${ApiConstants.unitsPath}/$unitId/comments', data: data);
  }
}

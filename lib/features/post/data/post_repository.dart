import '../../../core/constants/api_constants.dart';
import '../../../core/models/paginated_response.dart';
import '../../../core/network/api_client.dart';
import '../models/post.dart';

class PostRepository {
  final ApiClient _client = ApiClient.instance;

  Future<PaginatedResponse<Post>> getAll({String categories = 'announcement'}) async {
    final response = await _client.get<Map<String, dynamic>>(
      ApiConstants.postsPath,
      queryParameters: {'categories': categories, 'embed': 'author,categories'},
    );
    return PaginatedResponse.fromJson(response.data!, Post.fromJson);
  }

  Future<Post> getPost(int id) async {
    final response = await _client.get<Map<String, dynamic>>(
      '${ApiConstants.postsPath}/$id',
      queryParameters: {'append_fields': 'css_style'},
    );
    return Post.fromJson(PaginatedResponse.unwrap(response.data));
  }
}

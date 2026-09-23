import '../../../core/constants/api_constants.dart';
import '../../../core/models/paginated_response.dart';
import '../../../core/network/api_client.dart';
import '../models/banner_item.dart';

class HomeRepository {
  final ApiClient _client = ApiClient.instance;

  /// GET api/whoami/home — dashboard summary for the authenticated user.
  /// Response shape unverified; returned raw so the caller can pull
  /// whatever fields turn out to exist.
  Future<Map<String, dynamic>> getHomeSummary() async {
    final response = await _client.get<Map<String, dynamic>>(ApiConstants.homePath);
    return response.data ?? {};
  }

  Future<List<BannerItem>> getBanners() async {
    final response = await _client.get<Map<String, dynamic>>(ApiConstants.bannersPath);
    return PaginatedResponse.fromJson(response.data!, BannerItem.fromJson).items;
  }
}

import '../../../core/constants/api_constants.dart';
import '../../../core/models/paginated_response.dart';
import '../../../core/network/api_client.dart';
import '../models/notification_item.dart';

class NotificationRepository {
  final ApiClient _client = ApiClient.instance;

  Future<PaginatedResponse<NotificationItem>> getAll() async {
    final response = await _client.get<Map<String, dynamic>>(
      ApiConstants.notificationsPath,
    );
    return PaginatedResponse.fromJson(response.data!, NotificationItem.fromJson);
  }

  Future<void> markAsRead(String id) => _client.post(
    ApiConstants.notificationsPath,
    data: {'id': id},
  );
}

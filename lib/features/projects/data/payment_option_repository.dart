import '../../../core/constants/api_constants.dart';
import '../../../core/models/paginated_response.dart';
import '../../../core/network/api_client.dart';
import '../models/payment_option.dart';

class PaymentOptionRepository {
  final ApiClient _client = ApiClient.instance;

  Future<PaginatedResponse<PaymentOption>> getByUnitTypeId(int unitTypeId) async {
    final response = await _client.get<Map<String, dynamic>>(
      '${ApiConstants.unitTypesPath}/$unitTypeId/payment_options',
    );
    return PaginatedResponse.fromJson(response.data!, PaymentOption.fromJson);
  }

  Future<PaginatedResponse<PaymentOption>> getAll() async {
    final response = await _client.get<Map<String, dynamic>>(ApiConstants.paymentOptionsPath);
    return PaginatedResponse.fromJson(response.data!, PaymentOption.fromJson);
  }
}

import '../../../core/constants/api_constants.dart';
import '../../../core/models/paginated_response.dart';
import '../../../core/network/api_client.dart';
import '../models/unit_hold_request.dart';

class HoldRepository {
  final ApiClient _client = ApiClient.instance;

  Future<PaginatedResponse<UnitHoldRequest>> getAll({int? projectId}) async {
    final response = await _client.get<Map<String, dynamic>>(
      ApiConstants.unitHoldRequestsPath,
      queryParameters: {
        'embed': 'createdBy,actionedBy,unit,action',
        'project_id': ?projectId,
      },
    );
    return PaginatedResponse.fromJson(response.data!, UnitHoldRequest.fromJson);
  }

  /// [holdDay]: verified required against `Api/UnitHoldController`'s
  /// validation rules — an integer from 1 to 7.
  Future<UnitHoldRequest> create({
    required int unitId,
    required int holdDay,
    String? remark,
  }) async {
    assert(holdDay >= 1 && holdDay <= 7, 'hold_day must be between 1 and 7');
    final response = await _client.post<Map<String, dynamic>>(
      ApiConstants.unitHoldRequestsPath,
      data: {'unit_id': unitId, 'hold_day': holdDay, 'remark': ?remark},
    );
    return UnitHoldRequest.fromJson(PaginatedResponse.unwrap(response.data));
  }

  /// Verified against `Api/UnitHoldController@cancel`: only valid when the
  /// hold is currently APPROVED (not PENDING — that's what Reject is for).
  /// [reason] is optional server-side.
  Future<void> cancel(int id, {String? reason}) => _client.post(
    '${ApiConstants.unitHoldRequestsPath}/$id/cancel',
    data: {'action_reason': ?reason},
  );

  Future<void> approve(int id) => _client.post(
    '${ApiConstants.unitHoldRequestsPath}/$id/approve',
  );

  Future<void> reject(int id) => _client.post(
    '${ApiConstants.unitHoldRequestsPath}/$id/reject',
  );
}

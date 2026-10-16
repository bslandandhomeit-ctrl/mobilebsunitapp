import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:intl/intl.dart';

import '../../../core/constants/api_constants.dart';
import '../../../core/models/paginated_response.dart';
import '../../../core/network/api_client.dart';
import '../models/unit_contract_request.dart';
import 'contract_attachment.dart';

class ContractRequestRepository {
  final ApiClient _client = ApiClient.instance;

  Future<PaginatedResponse<UnitContractRequest>> getAll({
    int? projectId,
    int page = 1,
    String? term,
    int? unitTypeId,
  }) async {
    final response = await _client.get<Map<String, dynamic>>(
      ApiConstants.unitContractRequestsPath,
      queryParameters: {
        'embed': 'createdBy,unit.unitType.project,attachments,unitDepositRequest,actionedBy',
        'page': page,
        'term': ?term,
        'project_id': ?projectId,
        'unit_type_id': ?unitTypeId,
      },
    );
    return PaginatedResponse.fromJson(
      response.data!,
      UnitContractRequest.fromJson,
    );
  }

  Future<UnitContractRequest> get(int id) async {
    final response = await _client.get<Map<String, dynamic>>(
      '${ApiConstants.unitContractRequestsPath}/$id',
      queryParameters: {
        'embed': 'createdBy,unit.unitType.project,attachments,unitDepositRequest,actionedBy',
      },
    );
    return UnitContractRequest.fromJson(
      PaginatedResponse.unwrap(response.data),
    );
  }

  /// Verified against `Api/UnitContractRequestController::$validationRules`
  /// (2026-09-11): a contract request is created from an *approved deposit
  /// request* (`unit_deposit_request_id`, NOT a raw unit id — this is a
  /// correction from an earlier best-guess version of this method),
  /// requires `signed_at`/`start_payment_date` (dates, today or later), and
  /// at least one image `attachments` file.
  Future<UnitContractRequest> create({
    required int unitDepositRequestId,
    required DateTime signedAt,
    required DateTime startPaymentDate,
    required List<String> attachmentPaths,
    String? remark,
  }) async {
    assert(attachmentPaths.isNotEmpty, 'at least one attachment is required');
    final dateFormat = DateFormat('yyyy-MM-dd');
    final formData = FormData.fromMap({
      'unit_deposit_request_id': unitDepositRequestId,
      'signed_at': dateFormat.format(signedAt),
      'start_payment_date': dateFormat.format(startPaymentDate),
      'remark': ?remark,
      'attachments[]': [
        for (var index = 0; index < attachmentPaths.length; index++)
          await prepareContractAttachment(attachmentPaths[index], index),
      ],
    });
    final response = await _client.post<Map<String, dynamic>>(
      ApiConstants.unitContractRequestsPath,
      data: formData,
    );
    return UnitContractRequest.fromJson(
      PaginatedResponse.unwrap(response.data),
    );
  }

  /// Verified against `Api/UnitContractRequestController@cancel`: [reason]
  /// is required, not optional.
  Future<void> cancel(int id, {required String reason}) => _client.post(
    '${ApiConstants.unitContractRequestsPath}/$id/cancel',
    data: {'action_reason': reason},
  );

  /// Downloads the signed contract PDF. Fetched as raw bytes over the
  /// authenticated Dio client (not a plain WebView) since the endpoint
  /// requires a Bearer token — verified against
  /// `Api/ViewContractPdfController@get`.
  Future<List<int>> getContractPdfBytes(int contractId) async {
    try {
      final response = await _client.dio.get<List<int>>(
        ApiConstants.contractPdfPath(contractId),
        options: Options(
          responseType: ResponseType.bytes,
          receiveTimeout: const Duration(seconds: 45),
        ),
      );
      return response.data!;
    } on DioException catch (e) {
      final body = e.response?.data;
      if (body is List<int>) {
        try {
          e.response?.data = jsonDecode(utf8.decode(body));
        } catch (_) {}
      }
      throw _client.toApiException(e);
    }
  }
}

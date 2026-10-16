import '../../../core/constants/api_constants.dart';
import '../../../core/models/paginated_response.dart';
import '../../../core/network/api_client.dart';
import '../models/unit_deposit_request.dart';

class DepositRepository {
  final ApiClient _client = ApiClient.instance;

  Future<PaginatedResponse<UnitDepositRequest>> getAll({
    int? projectId,
    int page = 1,
    String? term,
    int? unitTypeId,
  }) async {
    final response = await _client.get<Map<String, dynamic>>(
      ApiConstants.unitDepositRequestsPath,
      queryParameters: {
        'embed': 'createdBy,unit.unitType.project,saleManager,unitController',
        'page': page,
        'term': ?term,
        'project_id': ?projectId,
        'unit_type_id': ?unitTypeId,
      },
    );
    return PaginatedResponse.fromJson(
      response.data!,
      UnitDepositRequest.fromJson,
    );
  }

  /// Verified against `Api/UnitDepositRequestController::$validationRules`
  /// + `$payment_option_validation_rules` (2026-09-11). Customer gender:
  /// 1 = Male, 2 = Female (confirmed against the original Android app's
  /// `gender_array` string resource + `Util.getGenderList` usage).
  ///
  /// Either pass [paymentOptionId] (a predefined option), or the full set
  /// of custom loan terms ([loanDuration], [interest], [specialDiscount],
  /// [isFirstPayment], [firstPaymentDuration], and one of
  /// [firstPaymentPercentage]/[firstPaymentAmount]) — not both.
  Future<UnitDepositRequest> create({
    required int unitId,
    required double depositAmount,
    required double otherDiscountAllowance,
    required String customerName,
    required int customerGender,
    required String customerPhoneNumber,
    String? customerName2,
    int? customerGender2,
    String? customerPhoneNumber2,
    String? remark,
    int? paymentOptionId,
    int? loanDuration,
    double? interest,
    double? specialDiscount,
    bool? isFirstPayment,
    int? firstPaymentDuration,
    double? firstPaymentPercentage,
    double? firstPaymentAmount,
  }) async {
    // Verified against `setPaymentOption()` (2026-09-12): this flag does
    // NOT mean "used a preset option" — it means "any payment terms were
    // provided at all" (preset OR custom). The backend defaults it to 1
    // when omitted entirely. Sending 0 here (as an earlier version of this
    // method did whenever `paymentOptionId` was null, i.e. every custom-
    // terms submission) makes the backend silently discard/zero every
    // custom loan field via `setPaymentOption()`, even though they were
    // submitted — confirmed on-device: a real deposit request created via
    // custom terms stored loan_duration=0/interest=0 regardless of what
    // was typed, later blocking contract request creation with "Payment
    // option cannot be none when requesting contract."
    final isSelectedPaymentOption =
        paymentOptionId != null || loanDuration != null;
    final response = await _client.post<Map<String, dynamic>>(
      ApiConstants.unitDepositRequestsPath,
      data: {
        'unit_id': unitId,
        'deposit_amount': depositAmount,
        'other_discount_allowance': otherDiscountAllowance,
        'customer_name': customerName,
        'customer_gender': customerGender,
        'customer_phone_number': customerPhoneNumber,
        'customer2_name': ?customerName2,
        'customer2_gender': ?customerGender2,
        'customer_phone_number2': ?customerPhoneNumber2,
        'remark': ?remark,
        'is_selected_payment_option': isSelectedPaymentOption ? 1 : 0,
        'payment_option_id': ?paymentOptionId,
        'loan_duration': ?loanDuration,
        'interest': ?interest,
        'special_discount': ?specialDiscount,
        'is_first_payment': ?(isFirstPayment == null
            ? null
            : (isFirstPayment ? 1 : 0)),
        'first_payment_duration': ?firstPaymentDuration,
        'first_payment_percentage': ?firstPaymentPercentage,
        'first_payment_amount': ?firstPaymentAmount,
      },
    );
    return UnitDepositRequest.fromJson(PaginatedResponse.unwrap(response.data));
  }

  /// Verified against `Api/UnitDepositRequestController@changeUnit`
  /// (2026-09-11): owner-only, and only while the deposit is APPROVED or
  /// OVERDUE. Reuses the same fields as [create] minus customer info
  /// (unchanged) — either move to a different unit (same
  /// `deposit_amount`/discount/payment terms apply to the new unit) or
  /// adjust discount/payment terms on the same unit.
  Future<UnitDepositRequest> changeUnit({
    required int id,
    required int unitId,
    required double otherDiscountAllowance,
    String? remark,
    int? paymentOptionId,
    int? loanDuration,
    double? interest,
    double? specialDiscount,
    bool? isFirstPayment,
    int? firstPaymentDuration,
    double? firstPaymentPercentage,
    double? firstPaymentAmount,
  }) async {
    // See the identical note in [create] — this must be true whenever
    // custom terms are provided, not just when a preset option is picked.
    final isSelectedPaymentOption =
        paymentOptionId != null || loanDuration != null;
    final response = await _client.post<Map<String, dynamic>>(
      '${ApiConstants.unitDepositRequestsPath}/$id/changeUnit',
      data: {
        'unit_id': unitId,
        'other_discount_allowance': otherDiscountAllowance,
        'remark': ?remark,
        'is_selected_payment_option': isSelectedPaymentOption ? 1 : 0,
        'payment_option_id': ?paymentOptionId,
        'loan_duration': ?loanDuration,
        'interest': ?interest,
        'special_discount': ?specialDiscount,
        'is_first_payment': ?(isFirstPayment == null
            ? null
            : (isFirstPayment ? 1 : 0)),
        'first_payment_duration': ?firstPaymentDuration,
        'first_payment_percentage': ?firstPaymentPercentage,
        'first_payment_amount': ?firstPaymentAmount,
      },
    );
    return UnitDepositRequest.fromJson(PaginatedResponse.unwrap(response.data));
  }

  /// Verified against `Api/UnitDepositRequestController@cancel`: restricted
  /// to the `unit_controller` role (not `sale_manager`, not the requesting
  /// agent — they can't cancel their own deposit through this endpoint),
  /// and [reason] is required, not optional.
  Future<void> cancel(int id, {required String reason}) => _client.post(
    '${ApiConstants.unitDepositRequestsPath}/$id/cancel',
    data: {'action_reason': reason},
  );

  Future<void> approve(int id) =>
      _client.post('${ApiConstants.unitDepositRequestsPath}/$id/approve');

  Future<void> reject(int id) =>
      _client.post('${ApiConstants.unitDepositRequestsPath}/$id/reject');
}

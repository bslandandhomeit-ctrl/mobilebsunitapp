import '../../../core/constants/api_constants.dart';
import '../../../core/network/api_client.dart';

/// The project's bank account and QR image, shown on the first step of the deposit so the customer
/// can scan it and pay. [qrUrl] is null when no QR was uploaded for that bank.
class PaymentQr {
  PaymentQr({
    this.bankName,
    this.accountName,
    this.accountNumber,
    this.qrUrl,
    this.companyName,
    this.companyNameEn,
    this.projectName,
    this.addressLine1,
    this.addressLine2,
    this.phone,
    this.email,
    this.website,
    this.logoUrl,
  });

  factory PaymentQr.fromJson(Map<String, dynamic> json) => PaymentQr(
    bankName: json['bank_name']?.toString(),
    accountName: json['account_name']?.toString(),
    accountNumber: json['account_number']?.toString(),
    qrUrl: json['qr_code_url']?.toString(),
    companyName: json['company_name']?.toString(),
    companyNameEn: json['company_name_en']?.toString(),
    projectName: json['project_name']?.toString(),
    addressLine1: json['address_line1']?.toString(),
    addressLine2: json['address_line2']?.toString(),
    phone: json['phone']?.toString(),
    email: json['email']?.toString(),
    website: json['website']?.toString(),
    logoUrl: json['logo_url']?.toString(),
  );

  final String? bankName, accountName, accountNumber, qrUrl;
  // Letterhead of the pre-receipt (same lines as the Be Loan receipt).
  final String? companyName, companyNameEn, projectName, addressLine1, addressLine2;
  final String? phone, email, website, logoUrl;
}

class PaymentQrRepository {
  final ApiClient _client = ApiClient.instance;

  Future<PaymentQr> forUnit(int unitId) async {
    final response = await _client.get<Map<String, dynamic>>(
      ApiConstants.unitPaymentQrPath(unitId),
    );
    final data = response.data?['data'];
    return PaymentQr.fromJson(data is Map<String, dynamic> ? data : {});
  }
}

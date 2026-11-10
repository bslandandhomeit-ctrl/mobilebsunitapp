import '../../../core/constants/api_constants.dart';
import '../../../core/network/api_client.dart';

/// A customer created in Be Loan and copied to BS Unit, offered in the deposit form so the agent does not
/// have to type the name, gender and phone. Picking is optional: the agent can always type a new customer.
class CustomerSuggestion {
  CustomerSuggestion({
    required this.id,
    required this.name,
    this.gender,
    this.phone,
    this.phone2,
    this.nationalId,
  });

  factory CustomerSuggestion.fromJson(Map<String, dynamic> json) {
    final gender = int.tryParse('${json['gender']}');
    return CustomerSuggestion(
      id: json['id'] as int,
      name: '${json['name'] ?? ''}',
      gender: (gender == 1 || gender == 2) ? gender : null,
      phone: json['phone_number']?.toString(),
      phone2: json['phone_number2']?.toString(),
      nationalId: json['national_id']?.toString(),
    );
  }

  final int id;
  final String name;
  final int? gender;
  final String? phone;
  final String? phone2;
  final String? nationalId;
}

class CustomerRepository {
  final ApiClient _client = ApiClient.instance;

  Future<List<CustomerSuggestion>> search(String term) async {
    final response = await _client.get<Map<String, dynamic>>(
      ApiConstants.customersPath,
      queryParameters: {'term': term, 'per_page': 20},
    );
    final data = response.data?['data'];
    if (data is! List) return const [];
    return data
        .whereType<Map<String, dynamic>>()
        .map(CustomerSuggestion.fromJson)
        .toList();
  }
}

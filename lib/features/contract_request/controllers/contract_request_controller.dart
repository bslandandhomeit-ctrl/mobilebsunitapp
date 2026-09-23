import 'package:get/get.dart';

import '../../../core/network/api_exception.dart';
import '../data/contract_request_repository.dart';
import '../models/unit_contract_request.dart';

class ContractRequestController extends GetxController {
  final ContractRequestRepository _repository = ContractRequestRepository();

  final isLoading = false.obs;
  final errorMessage = RxnString();
  final requests = <UnitContractRequest>[].obs;
  final searchQuery = ''.obs;

  List<UnitContractRequest> get filteredRequests {
    final query = searchQuery.value.trim().toLowerCase();
    if (query.isEmpty) return requests;
    return requests.where((r) => (r.unitCode ?? '').toLowerCase().contains(query)).toList();
  }

  @override
  void onInit() {
    super.onInit();
    load();
  }

  Future<void> load() async {
    isLoading.value = true;
    errorMessage.value = null;
    try {
      requests.value = (await _repository.getAll()).items;
    } on ApiException catch (e) {
      errorMessage.value = e.message;
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> cancel(int id, {required String reason}) async {
    await _repository.cancel(id, reason: reason);
    await load();
  }
}

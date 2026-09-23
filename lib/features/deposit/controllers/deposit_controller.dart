import 'package:get/get.dart';

import '../../../core/network/api_exception.dart';
import '../data/deposit_repository.dart';
import '../models/unit_deposit_request.dart';

class DepositController extends GetxController {
  final DepositRepository _repository = DepositRepository();

  final isLoading = false.obs;
  final errorMessage = RxnString();
  final requests = <UnitDepositRequest>[].obs;
  final searchQuery = ''.obs;

  List<UnitDepositRequest> get filteredRequests {
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

  Future<void> approve(int id) async {
    await _repository.approve(id);
    await load();
  }

  Future<void> reject(int id) async {
    await _repository.reject(id);
    await load();
  }
}

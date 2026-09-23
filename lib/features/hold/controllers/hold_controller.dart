import 'package:get/get.dart';

import '../../../core/network/api_exception.dart';
import '../data/hold_repository.dart';
import '../models/unit_hold_request.dart';

class HoldController extends GetxController {
  final HoldRepository _repository = HoldRepository();

  final isLoading = false.obs;
  final errorMessage = RxnString();
  final requests = <UnitHoldRequest>[].obs;
  final searchQuery = ''.obs;

  List<UnitHoldRequest> get filteredRequests {
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

  Future<void> cancel(int id, {String? reason}) async {
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

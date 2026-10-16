import 'dart:async';

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
  final hasMore = false.obs;
  final isLoadingMore = false.obs;
  int _page = 1, _generation = 0;
  Timer? _debounce;
  void search(String value) {
    searchQuery.value = value;
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 350), load);
  }

  @override
  void onClose() {
    _debounce?.cancel();
    super.onClose();
  }

  Future<void> loadMore() async {
    if (!hasMore.value || isLoadingMore.value || isLoading.value) return;
    final generation = _generation;
    isLoadingMore.value = true;
    try {
      final result = await _repository.getAll(
        page: _page + 1,
        term: searchQuery.value,
      );
      if (generation != _generation) return;
      _page++;
      requests.addAll(result.items);
      hasMore.value = _page < (result.lastPage ?? _page);
    } on ApiException catch (e) {
      errorMessage.value = e.message;
    } finally {
      isLoadingMore.value = false;
    }
  }

  List<UnitHoldRequest> get filteredRequests {
    final query = searchQuery.value.trim().toLowerCase();
    if (query.isEmpty) return requests;
    return requests
        .where((r) => (r.unitCode ?? '').toLowerCase().contains(query))
        .toList();
  }

  @override
  void onInit() {
    super.onInit();
    load();
  }

  Future<void> load() async {
    final generation = ++_generation;
    isLoading.value = true;
    errorMessage.value = null;
    try {
      final result = await _repository.getAll(term: searchQuery.value);
      if (generation != _generation) return;
      requests.value = result.items;
      _page = 1;
      hasMore.value = _page < (result.lastPage ?? _page);
    } on ApiException catch (e) {
      errorMessage.value = e.message;
    } finally {
      if (generation == _generation) isLoading.value = false;
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

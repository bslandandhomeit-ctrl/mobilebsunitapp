import 'package:get/get.dart';

import '../../../core/network/api_exception.dart';
import '../data/projects_repository.dart';
import '../models/project.dart';
import '../models/unit.dart';

class ProjectDetailController extends GetxController {
  ProjectDetailController(this.project);

  final Project project;
  final ProjectsRepository _repository = ProjectsRepository();

  final isLoading = false.obs;
  final isLoadingMore = false.obs;
  final errorMessage = RxnString();
  final units = <Unit>[].obs;
  final searchQuery = ''.obs;

  List<Unit> get filteredUnits {
    final query = searchQuery.value.trim().toLowerCase();
    if (query.isEmpty) return units;
    return units.where((u) => u.code.toLowerCase().contains(query)).toList();
  }

  int _page = 1;
  bool _hasMore = true;
  bool get hasMore => _hasMore;

  @override
  void onInit() {
    super.onInit();
    loadUnits();
  }

  Future<void> loadUnits() async {
    isLoading.value = true;
    errorMessage.value = null;
    _page = 1;
    try {
      final result = await _repository.getUnitsForProject(project.id, page: _page);
      units.value = result.items;
      _hasMore = result.lastPage == null || _page < result.lastPage!;
    } on ApiException catch (e) {
      errorMessage.value = e.message;
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> loadMore() async {
    if (isLoadingMore.value || !_hasMore) return;
    isLoadingMore.value = true;
    try {
      final nextPage = _page + 1;
      final result = await _repository.getUnitsForProject(project.id, page: nextPage);
      units.addAll(result.items);
      _page = nextPage;
      _hasMore = result.lastPage == null || _page < result.lastPage!;
    } on ApiException catch (e) {
      errorMessage.value = e.message;
    } finally {
      isLoadingMore.value = false;
    }
  }
}

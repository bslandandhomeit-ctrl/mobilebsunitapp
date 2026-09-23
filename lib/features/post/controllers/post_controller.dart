import 'package:get/get.dart';

import '../../../core/network/api_exception.dart';
import '../data/post_repository.dart';
import '../models/post.dart';

class PostController extends GetxController {
  final PostRepository _repository = PostRepository();

  final isLoading = false.obs;
  final errorMessage = RxnString();
  final posts = <Post>[].obs;

  @override
  void onInit() {
    super.onInit();
    load();
  }

  Future<void> load() async {
    isLoading.value = true;
    errorMessage.value = null;
    try {
      posts.value = (await _repository.getAll()).items;
    } on ApiException catch (e) {
      errorMessage.value = e.message;
    } finally {
      isLoading.value = false;
    }
  }
}

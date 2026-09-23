import 'package:get/get.dart';

import '../../../core/network/api_exception.dart';
import '../data/notification_repository.dart';
import '../models/notification_item.dart';

class NotificationController extends GetxController {
  final NotificationRepository _repository = NotificationRepository();

  final isLoading = false.obs;
  final errorMessage = RxnString();
  final notifications = <NotificationItem>[].obs;

  @override
  void onInit() {
    super.onInit();
    load();
  }

  Future<void> load() async {
    isLoading.value = true;
    errorMessage.value = null;
    try {
      notifications.value = (await _repository.getAll()).items;
    } on ApiException catch (e) {
      errorMessage.value = e.message;
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> markAsRead(NotificationItem item) async {
    if (item.isRead) return;
    await _repository.markAsRead(item.id);
    await load();
  }
}

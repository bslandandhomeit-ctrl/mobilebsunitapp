import 'package:get/get.dart';

import '../../../app/routes/app_routes.dart';
import '../../../core/storage/local_storage.dart';

class SplashController extends GetxController {
  @override
  void onReady() {
    super.onReady();
    _redirect();
  }

  Future<void> _redirect() async {
    await Future.delayed(const Duration(milliseconds: 600));
    Get.offAllNamed(
      LocalStorage.isLoggedIn ? AppRoutes.home : AppRoutes.login,
    );
  }
}

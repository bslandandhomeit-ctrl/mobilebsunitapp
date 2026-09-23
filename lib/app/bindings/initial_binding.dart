import 'package:get/get.dart';

import '../../features/auth/controllers/auth_controller.dart';

/// Registered once at app start — controllers/services every feature
/// may need (auth, session) live here.
class InitialBinding extends Bindings {
  @override
  void dependencies() {
    Get.put(AuthController(), permanent: true);
  }
}

import 'package:get/get.dart';

import '../../features/auth/views/login_view.dart';
import '../../features/home/controllers/home_controller.dart';
import '../../features/splash/controllers/splash_controller.dart';
import '../../features/splash/views/splash_view.dart';
import '../views/main_shell.dart';
import 'app_routes.dart';

class AppPages {
  AppPages._();

  static const initial = AppRoutes.splash;

  static final routes = <GetPage>[
    GetPage(
      name: AppRoutes.splash,
      page: () => const SplashView(),
      binding: BindingsBuilder(() {
        Get.put(SplashController());
      }),
    ),
    GetPage(name: AppRoutes.login, page: () => const LoginView()),
    GetPage(
      name: AppRoutes.home,
      page: () => const MainShell(),
      binding: BindingsBuilder(() {
        Get.put(HomeController());
      }),
    ),
  ];
}

import 'package:flutter/material.dart';

import '../../../core/widgets/app_ui.dart';

import 'package:get/get.dart';

import '../controllers/splash_controller.dart';

class SplashView extends GetView<SplashController> {
  const SplashView({super.key});

  @override
  Widget build(BuildContext context) {
    return AppPage(
      body: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Image.asset(
              'assets/icon/splash_logo.png',
              width: 120,
              height: 60,
              semanticLabel: 'BS',
            ),
            const SizedBox(height: 24),
            Text('BS Units', style: Theme.of(context).textTheme.headlineSmall),
            const SizedBox(height: 32),
            const SizedBox(
              width: 28,
              height: 28,
              child: CircularProgressIndicator(strokeWidth: 2),
            ),
          ],
        ),
      ),
    );
  }
}

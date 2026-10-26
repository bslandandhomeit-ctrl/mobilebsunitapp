import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../auth/widgets/auth_backdrop.dart';
import '../controllers/splash_controller.dart';

class SplashView extends GetView<SplashController> {
  const SplashView({super.key});
  @override
  Widget build(BuildContext context) => Scaffold(
    body: AuthBackdrop(
      splash: true,
      child: SafeArea(
        child: Center(
          child: TweenAnimationBuilder<double>(
            tween: Tween(begin: 0, end: 1),
            duration: const Duration(milliseconds: 1100),
            curve: Curves.easeOutCubic,
            builder: (context, value, child) => Opacity(
              opacity: value,
              child: Transform.translate(
                offset: Offset(0, 20 * (1 - value)),
                child: child,
              ),
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const SizedBox(height: 120),
                const Text(
                  'WELCOME TO',
                  style: TextStyle(
                    fontFamily: 'Manrope',
                    fontSize: 11,
                    letterSpacing: 4,
                    color: authGreen,
                  ),
                ),
                const SizedBox(height: 14),
                const AuthWordmark(size: 72),
                const SizedBox(height: 16),
                const Text(
                  'A place to belong. A future to build.',
                  style: TextStyle(
                    fontFamily: 'Manrope',
                    fontSize: 12,
                    color: Color(0xFF61776D),
                  ),
                ),
                const SizedBox(height: 40),
                SizedBox(
                  width: 90,
                  child: TweenAnimationBuilder<double>(
                    tween: Tween(begin: 0, end: 1),
                    duration: const Duration(milliseconds: 1700),
                    builder: (context, value, _) => LinearProgressIndicator(
                      value: value,
                      minHeight: 2,
                      borderRadius: BorderRadius.circular(2),
                      color: authGreen,
                      backgroundColor: const Color(0xFFD1DFD0),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    ),
  );
}

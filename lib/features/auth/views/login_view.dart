import 'dart:ui';

import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:package_info_plus/package_info_plus.dart';

import '../../../l10n/app_localizations.dart';
import '../controllers/auth_controller.dart';
import '../widgets/auth_backdrop.dart';
import 'forgot_password_view.dart';
import 'register_view.dart';

class LoginView extends GetView<AuthController> {
  const LoginView({super.key});

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context)!;
    final theme = Theme.of(context);
    final authTheme = theme.copyWith(
      textTheme: theme.textTheme.apply(fontFamily: 'Manrope'),
      colorScheme: theme.colorScheme.copyWith(primary: authGreen),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: Colors.white.withValues(alpha: .7),
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 18,
          vertical: 18,
        ),
        labelStyle: const TextStyle(color: Color(0xFF61776D), fontSize: 14),
        prefixIconColor: const Color(0xFF61776D),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(16),
          borderSide: const BorderSide(color: Color(0xFFD6E1D8)),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(16),
          borderSide: const BorderSide(color: Color(0xFFD6E1D8)),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(16),
          borderSide: const BorderSide(color: authGreen, width: 1.5),
        ),
      ),
    );
    return Theme(
      data: authTheme,
      child: Scaffold(
        backgroundColor: const Color(0xFFEEF3EA),
        body: AuthBackdrop(
          child: SafeArea(
            child: LayoutBuilder(
              builder: (context, constraints) => SingleChildScrollView(
                padding: const EdgeInsets.fromLTRB(24, 0, 24, 28),
                child: Center(
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 440),
                    child: TweenAnimationBuilder<double>(
                      tween: Tween(begin: 0, end: 1),
                      duration: const Duration(milliseconds: 850),
                      curve: Curves.easeOutCubic,
                      builder: (context, value, child) => Opacity(
                        opacity: value,
                        child: Transform.translate(
                          offset: Offset(0, 26 * (1 - value)),
                          child: child,
                        ),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          SizedBox(
                            height: (constraints.maxHeight * .24).clamp(
                              100,
                              220,
                            ),
                          ),
                          const AuthWordmark(),
                          const SizedBox(height: 10),
                          const Text(
                            'Your next chapter starts here.',
                            textAlign: TextAlign.center,
                            style: TextStyle(
                              fontFamily: 'Manrope',
                              color: Color(0xFF526C5D),
                              fontSize: 13,
                              letterSpacing: .2,
                            ),
                          ),
                          const SizedBox(height: 28),
                          Container(
                            decoration: BoxDecoration(
                              borderRadius: BorderRadius.circular(28),
                              boxShadow: const [
                                BoxShadow(
                                  color: Color(0x161C4835),
                                  blurRadius: 40,
                                  offset: Offset(0, 18),
                                ),
                              ],
                            ),
                            child: ClipRRect(
                              borderRadius: BorderRadius.circular(28),
                              child: BackdropFilter(
                                filter: ImageFilter.blur(
                                  sigmaX: 20,
                                  sigmaY: 20,
                                ),
                                child: Container(
                                  padding: const EdgeInsets.all(24),
                                  decoration: BoxDecoration(
                                    color: Colors.white.withValues(alpha: .76),
                                    borderRadius: BorderRadius.circular(28),
                                    border: Border.all(
                                      color: Colors.white.withValues(alpha: .9),
                                    ),
                                  ),
                                  child: AutofillGroup(
                                    child: Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.stretch,
                                      children: [
                                        const Text(
                                          'Welcome back',
                                          style: TextStyle(
                                            fontSize: 24,
                                            fontWeight: FontWeight.w700,
                                            color: authInk,
                                            letterSpacing: -.6,
                                          ),
                                        ),
                                        const SizedBox(height: 7),
                                        const Text(
                                          'Sign in to explore your possibilities.',
                                          style: TextStyle(
                                            color: Color(0xFF61776D),
                                            fontSize: 13,
                                            height: 1.5,
                                          ),
                                        ),
                                        const SizedBox(height: 24),
                                        TextField(
                                          controller:
                                              controller.usernameController,
                                          keyboardType: TextInputType.phone,
                                          autofillHints: const [
                                            AutofillHints.username,
                                          ],
                                          textInputAction: TextInputAction.next,
                                          decoration: InputDecoration(
                                            labelText: l.phoneNumber,
                                            prefixIcon: const Icon(
                                              Icons.phone_outlined,
                                              size: 20,
                                            ),
                                          ),
                                        ),
                                        const SizedBox(height: 16),
                                        Obx(
                                          () => TextField(
                                            controller:
                                                controller.passwordController,
                                            obscureText: !controller
                                                .isPasswordVisible
                                                .value,
                                            autocorrect: false,
                                            enableSuggestions: false,
                                            autofillHints: const [
                                              AutofillHints.password,
                                            ],
                                            textInputAction:
                                                TextInputAction.done,
                                            onSubmitted: (_) {
                                              if (!controller.isLoading.value) {
                                                controller.login();
                                              }
                                            },
                                            decoration: InputDecoration(
                                              labelText: l.password,
                                              prefixIcon: const Icon(
                                                Icons.lock_outline_rounded,
                                                size: 20,
                                              ),
                                              suffixIcon: IconButton(
                                                tooltip:
                                                    controller
                                                        .isPasswordVisible
                                                        .value
                                                    ? l.hidePassword
                                                    : l.showPassword,
                                                onPressed: controller
                                                    .isPasswordVisible
                                                    .toggle,
                                                icon: Icon(
                                                  controller
                                                          .isPasswordVisible
                                                          .value
                                                      ? Icons
                                                            .visibility_off_outlined
                                                      : Icons
                                                            .visibility_outlined,
                                                  size: 20,
                                                ),
                                              ),
                                            ),
                                          ),
                                        ),
                                        Align(
                                          alignment: Alignment.centerRight,
                                          child: TextButton(
                                            onPressed: () => Get.to(
                                              () => const ForgotPasswordView(),
                                            ),
                                            style: TextButton.styleFrom(
                                              foregroundColor: authGreen,
                                            ),
                                            child: Text(l.forgotPassword),
                                          ),
                                        ),
                                        Obx(
                                          () =>
                                              controller.errorMessage.value ==
                                                  null
                                              ? const SizedBox.shrink()
                                              : Padding(
                                                  padding:
                                                      const EdgeInsets.only(
                                                        bottom: 16,
                                                      ),
                                                  child: Text(
                                                    controller
                                                        .errorMessage
                                                        .value!,
                                                    style: TextStyle(
                                                      color: theme
                                                          .colorScheme
                                                          .error,
                                                      fontSize: 13,
                                                    ),
                                                  ),
                                                ),
                                        ),
                                        Obx(
                                          () => FilledButton(
                                            onPressed:
                                                controller.isLoading.value
                                                ? null
                                                : controller.login,
                                            style: FilledButton.styleFrom(
                                              backgroundColor: authGreen,
                                              foregroundColor: Colors.white,
                                              minimumSize:
                                                  const Size.fromHeight(54),
                                              shape: RoundedRectangleBorder(
                                                borderRadius:
                                                    BorderRadius.circular(16),
                                              ),
                                            ),
                                            child: controller.isLoading.value
                                                ? const SizedBox(
                                                    width: 22,
                                                    height: 22,
                                                    child:
                                                        CircularProgressIndicator(
                                                          strokeWidth: 2,
                                                          color: Colors.white,
                                                        ),
                                                  )
                                                : Row(
                                                    mainAxisAlignment:
                                                        MainAxisAlignment
                                                            .center,
                                                    children: [
                                                      Text(l.logIn),
                                                      const SizedBox(width: 12),
                                                      const Icon(
                                                        Icons
                                                            .arrow_forward_rounded,
                                                        size: 18,
                                                      ),
                                                    ],
                                                  ),
                                          ),
                                        ),
                                        const SizedBox(height: 20),
                                        Row(
                                          children: [
                                            Expanded(
                                              child: Divider(
                                                color: authGreen.withValues(
                                                  alpha: .13,
                                                ),
                                              ),
                                            ),
                                            const Padding(
                                              padding: EdgeInsets.symmetric(
                                                horizontal: 14,
                                              ),
                                              child: Text(
                                                'NEW HERE?',
                                                style: TextStyle(
                                                  fontSize: 10,
                                                  letterSpacing: 1.5,
                                                  color: Color(0xFF74877A),
                                                ),
                                              ),
                                            ),
                                            Expanded(
                                              child: Divider(
                                                color: authGreen.withValues(
                                                  alpha: .13,
                                                ),
                                              ),
                                            ),
                                          ],
                                        ),
                                        const SizedBox(height: 14),
                                        OutlinedButton(
                                          onPressed: () => Get.to(
                                            () => const RegisterView(),
                                          ),
                                          style: OutlinedButton.styleFrom(
                                            foregroundColor: authGreen,
                                            minimumSize: const Size.fromHeight(
                                              50,
                                            ),
                                            side: const BorderSide(
                                              color: Color(0xFFC2D3C7),
                                            ),
                                            shape: RoundedRectangleBorder(
                                              borderRadius:
                                                  BorderRadius.circular(16),
                                            ),
                                          ),
                                          child: const Text('Register'),
                                        ),
                                      ],
                                    ),
                                  ),
                                ),
                              ),
                            ),
                          ),
                          const SizedBox(height: 22),
                          const Text(
                            'A place to belong. A future to build.',
                            textAlign: TextAlign.center,
                            style: TextStyle(
                              fontSize: 11,
                              color: Color(0xFF61776D),
                              letterSpacing: .3,
                            ),
                          ),
                          const SizedBox(height: 12),
                          const _AppVersionLabel(),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// App version shown at the bottom of the login screen, e.g. "v1.0.0 (23)".
class _AppVersionLabel extends StatefulWidget {
  const _AppVersionLabel();
  @override
  State<_AppVersionLabel> createState() => _AppVersionLabelState();
}

class _AppVersionLabelState extends State<_AppVersionLabel> {
  String _version = '';
  @override
  void initState() {
    super.initState();
    PackageInfo.fromPlatform().then((info) {
      if (mounted) {
        setState(() => _version = 'v${info.version} (${info.buildNumber})');
      }
    });
  }

  @override
  Widget build(BuildContext context) => Text(
    _version,
    textAlign: TextAlign.center,
    style: Theme.of(context).textTheme.bodySmall
        ?.copyWith(color: Theme.of(context).hintColor),
  );
}

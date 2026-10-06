import '../../../core/constants/api_constants.dart';

import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:onesignal_flutter/onesignal_flutter.dart';

import '../../../app/routes/app_routes.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/storage/local_storage.dart';
import '../data/auth_repository.dart';
import '../models/user.dart';

class AuthController extends GetxController {
  final AuthRepository _repository = AuthRepository();

  final usernameController = TextEditingController();
  final passwordController = TextEditingController();
  final isPasswordVisible = false.obs;

  final isLoading = false.obs;
  final errorMessage = RxnString();
  final Rxn<AppUser> currentUser = Rxn<AppUser>();

  void Function(OSPushSubscriptionChangedState)? _pushObserver;

  @override
  void onInit() {
    super.onInit();
    if (LocalStorage.isLoggedIn) {
      _repository.getAuthenticatedUser().then(
        (user) => currentUser.value = user,
        onError: (_) {},
      );
      _registerPushSubscription();
    }
    _watchPushSubscriptionChanges();
  }

  Future<void> login() async {
    if (usernameController.text.trim().isEmpty ||
        passwordController.text.isEmpty) {
      errorMessage.value = 'Please enter your phone number and password.';
      return;
    }

    isLoading.value = true;
    errorMessage.value = null;
    try {
      await _repository.login(
        username: usernameController.text.trim(),
        password: passwordController.text,
      );
      currentUser.value = await _repository.getAuthenticatedUser();
      await _registerPushSubscription();
      Get.offAllNamed(AppRoutes.home);
    } on ApiException catch (e) {
      errorMessage.value = e.message;
    } finally {
      isLoading.value = false;
    }
  }

  /// Registers this device's OneSignal subscription id against the
  /// current Passport token — without this, push notifications never
  /// reach the device even though OneSignal itself is initialized. The
  /// id may not be available yet right after login (async subscription
  /// setup), so [_watchPushSubscriptionChanges] also retries via an
  /// observer whenever it changes.
  Future<void> _registerPushSubscription() async {
    if (!ApiConstants.isProduction) return;
    final playerId = OneSignal.User.pushSubscription.id;
    if (playerId == null || playerId.length != 36) return;
    try {
      await _repository.updateOnesignalPlayerId(playerId);
    } catch (_) {
      // Non-fatal — the observer will retry on the next subscription change.
    }
  }

  void _watchPushSubscriptionChanges() {
    if (!ApiConstants.isProduction) return;
    _pushObserver = (_) {
      if (LocalStorage.isLoggedIn) _registerPushSubscription();
    };
    OneSignal.User.pushSubscription.addObserver(_pushObserver!);
  }

  Future<void> logout() async {
    await _repository.logout();
    currentUser.value = null;
    Get.offAllNamed(AppRoutes.login);
  }

  @override
  void onClose() {
    if (_pushObserver != null) {
      OneSignal.User.pushSubscription.removeObserver(_pushObserver!);
    }
    usernameController.dispose();
    passwordController.dispose();
    super.onClose();
  }
}

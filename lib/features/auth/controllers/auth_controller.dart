
import 'package:flutter/material.dart';
import 'package:get/get.dart';

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
  final userLoadError = RxnString();

  @override
  void onInit() {
    super.onInit();
    if (LocalStorage.isLoggedIn) {
      loadCurrentUser();
    }
  }

  Future<void> loadCurrentUser() async {
    userLoadError.value = null;
    try {
      currentUser.value = await _repository.getAuthenticatedUser();
    } on ApiException catch (e) {
      if (e.statusCode == 401) {
        await LocalStorage.clearAuth();
        currentUser.value = null;
        Get.offAllNamed(AppRoutes.login);
      } else {
        userLoadError.value = e.message;
      }
    }
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
      Get.offAllNamed(AppRoutes.home);
    } on ApiException catch (e) {
      await LocalStorage.clearAuth();
      currentUser.value = null;
      errorMessage.value = e.message;
    } finally {
      isLoading.value = false;
    }
  }

  /// The account was just deleted on the server (all its sign-ins are revoked): clear what the phone holds and go to login.
  Future<void> signOutLocally() async {
    await LocalStorage.clearAuth();
    currentUser.value = null;
    Get.offAllNamed(AppRoutes.login);
  }

  Future<void> logout() async {
    try {
      await _repository.logout();
    } finally {
      currentUser.value = null;
      Get.offAllNamed(AppRoutes.login);
    }
  }

  @override
  void onClose() {
    usernameController.dispose();
    passwordController.dispose();
    super.onClose();
  }
}

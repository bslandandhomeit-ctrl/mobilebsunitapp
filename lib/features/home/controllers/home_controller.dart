import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:package_info_plus/package_info_plus.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/network/app_version_repository.dart';
import '../../../l10n/app_localizations.dart';
import '../../projects/data/projects_repository.dart';
import '../../projects/models/project.dart';
import '../data/home_repository.dart';
import '../models/banner_item.dart';

class HomeController extends GetxController {
  final HomeRepository _homeRepository = HomeRepository();
  final ProjectsRepository _projectsRepository = ProjectsRepository();
  final AppVersionRepository _appVersionRepository = AppVersionRepository();

  final isLoading = false.obs;
  final errorMessage = RxnString();
  final banners = <BannerItem>[].obs;
  final projects = <Project>[].obs;

  @override
  void onInit() {
    super.onInit();
    loadHome();
    // _checkForForcedUpdate();
  }

  Future<void> loadHome() async {
    isLoading.value = true;
    errorMessage.value = null;
    try {
      final bannersFuture = _homeRepository.getBanners();
      final projectsFuture = _projectsRepository.getProjects();
      banners.value = await bannersFuture;
      projects.value = (await projectsFuture).items;
    } on ApiException catch (e) {
      errorMessage.value = e.message;
    } finally {
      isLoading.value = false;
    }
  }

  // Kept for when the forced-update check is switched back on (see onInit).
  // ignore: unused_element
  Future<void> _checkForForcedUpdate() async {
    final latest = await _appVersionRepository.getLatestBuild();
    if (latest == null || !latest.forcedUpdate) return;

    final info = await PackageInfo.fromPlatform();
    final currentBuild = int.tryParse(info.buildNumber) ?? 0;
    if (currentBuild >= latest.build) return;

    if (Get.context == null) return;
    final l10n = AppLocalizations.of(Get.context!)!;
    Get.dialog(
      PopScope(
        canPop: false,
        child: AlertDialog(
          title: Text(l10n.updateRequired),
          content: Text(
            latest.description?.isNotEmpty == true
                ? latest.description!
                : l10n.updateRequiredMessage(latest.version),
          ),
          actions: [
            // TODO: launch the real Play Store/App Store listing once the
            // app is published — no public listing exists yet to link to.
            TextButton(onPressed: () {}, child: Text(l10n.update)),
          ],
        ),
      ),
      barrierDismissible: false,
    );
  }
}

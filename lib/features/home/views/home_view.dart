import 'banner_carousel.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../app/theme/app_theme.dart';
import '../../../core/widgets/app_ui.dart';
import '../../../l10n/app_localizations.dart';
import '../../auth/controllers/auth_controller.dart';
import '../../projects/controllers/project_detail_controller.dart';
import '../../projects/models/project.dart';
import '../../projects/views/project_detail_view.dart';
import '../../projects/views/unit_search_view.dart';
import '../controllers/home_controller.dart';

class HomeView extends GetView<HomeController> {
  const HomeView({super.key});
  @override
  Widget build(BuildContext context) {
    final auth = Get.find<AuthController>();
    final l10n = AppLocalizations.of(context)!;
    return AppPage(
      appBar: AppBar(
        title: Row(
          children: [
            Image.asset(
              'assets/icon/splash_logo.png',
              width: 44,
              height: 24,
              semanticLabel: 'BS',
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Text(l10n.appTitle, overflow: TextOverflow.ellipsis),
            ),
          ],
        ),
        actions: [
          IconButton(
            onPressed: auth.logout,
            tooltip: l10n.logOut,
            icon: const Icon(Icons.logout_rounded),
          ),
          const SizedBox(width: 8),
        ],
      ),
      body: Obx(() {
        if (controller.isLoading.value &&
            controller.projects.isEmpty &&
            controller.banners.isEmpty) {
          return const Center(child: CircularProgressIndicator());
        }
        if (controller.errorMessage.value != null &&
            controller.projects.isEmpty) {
          return AppEmptyState(
            message: controller.errorMessage.value!,
            icon: Icons.cloud_off_outlined,
            action: OutlinedButton.icon(
              onPressed: controller.loadHome,
              icon: const Icon(Icons.refresh),
              label: Text(l10n.retry),
            ),
          );
        }
        return RefreshIndicator(
          onRefresh: controller.loadHome,
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(20, 8, 20, 28),
            children: [
              Container(
                padding: const EdgeInsets.all(24),
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(24),
                  gradient: const LinearGradient(
                    colors: [Color(0xFF123B61), Color(0xFF176FA8)],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Icon(
                      Icons.apartment_rounded,
                      color: Color(0xFFA6D5F4),
                      size: 32,
                    ),
                    const SizedBox(height: 18),
                    Text(
                      l10n.projects,
                      style: Theme.of(context).textTheme.headlineSmall
                          ?.copyWith(color: Colors.white),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      l10n.appTitle,
                      style: const TextStyle(
                        color: Color(0xFFD8EBF8),
                        fontSize: 14,
                      ),
                    ),
                    const SizedBox(height: 24),
                    Material(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(14),
                      child: InkWell(
                        borderRadius: BorderRadius.circular(14),
                        onTap: () => Get.to(() => const UnitSearchView()),
                        child: Padding(
                          padding: const EdgeInsets.all(16),
                          child: Row(
                            children: [
                              const Icon(
                                Icons.search_rounded,
                                color: AppTheme.brand,
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Text(
                                  l10n.searchByUnitCode,
                                  style: const TextStyle(color: AppTheme.muted),
                                ),
                              ),
                              const Icon(
                                Icons.arrow_forward_rounded,
                                color: AppTheme.brand,
                                size: 20,
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              if (controller.banners.isNotEmpty) ...[
                const SizedBox(height: 24),
                BannerCarousel(banners: controller.banners.toList()),
              ],
              const SizedBox(height: 28),
              AppSectionTitle(l10n.projects, count: controller.projects.length),
              if (controller.projects.isEmpty)
                AppEmptyState(message: l10n.noProjectsFound)
              else
                ...controller.projects.map(
                  (p) => Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: ProjectCard(project: p),
                  ),
                ),
            ],
          ),
        );
      }),
    );
  }
}

class ProjectCard extends StatelessWidget {
  const ProjectCard({super.key, required this.project});
  final Project project;
  @override
  Widget build(BuildContext context) {
    return Card(
      child: InkWell(
        onTap: () => Get.to(
          () => const ProjectDetailView(),
          binding: BindingsBuilder(() {
            Get.put(ProjectDetailController(project));
          }),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (project.featureImageUrl?.isNotEmpty == true)
              AppImage(url: project.featureImageUrl, height: 150),
            Padding(
              padding: const EdgeInsets.all(18),
              child: Row(
                children: [
                  SizedBox(
                    width: 56,
                    height: 56,
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(14),
                      child: AppImage(url: project.logoUrl, height: 56),
                    ),
                  ),
                  const SizedBox(width: 16),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          project.displayName,
                          style: Theme.of(context).textTheme.titleMedium,
                        ),
                        if (project.shortCode?.isNotEmpty == true) ...[
                          const SizedBox(height: 6),
                          Text(
                            project.shortCode!,
                            style: Theme.of(context).textTheme.bodyMedium,
                          ),
                        ],
                      ],
                    ),
                  ),
                  const SizedBox(width: 8),
                  const Icon(
                    Icons.arrow_forward_rounded,
                    size: 20,
                    color: AppTheme.brand,
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

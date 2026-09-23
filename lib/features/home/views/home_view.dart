import 'package:cached_network_image/cached_network_image.dart';
import 'package:carousel_slider/carousel_slider.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../auth/controllers/auth_controller.dart';
import '../../projects/controllers/project_detail_controller.dart';
import '../../projects/models/project.dart';
import '../../projects/views/project_detail_view.dart';
import '../../projects/views/unit_search_view.dart';
import '../../../l10n/app_localizations.dart';
import '../controllers/home_controller.dart';

class HomeView extends GetView<HomeController> {
  const HomeView({super.key});

  @override
  Widget build(BuildContext context) {
    final auth = Get.find<AuthController>();
    final l10n = AppLocalizations.of(context)!;
    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.appTitle),
        actions: [
          IconButton(
            onPressed: () => Get.to(() => const UnitSearchView()),
            icon: const Icon(Icons.search),
          ),
          IconButton(onPressed: auth.logout, icon: const Icon(Icons.logout)),
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
          return _ErrorState(
            message: controller.errorMessage.value!,
            onRetry: controller.loadHome,
          );
        }
        return RefreshIndicator(
          onRefresh: controller.loadHome,
          child: ListView(
            padding: const EdgeInsets.only(bottom: 24),
            children: [
              if (controller.banners.isNotEmpty) _BannerCarousel(controller: controller),
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
                child: Text(
                  l10n.projects,
                  style: Theme.of(context).textTheme.titleLarge,
                ),
              ),
              if (controller.projects.isEmpty)
                Padding(
                  padding: const EdgeInsets.all(24),
                  child: Center(child: Text(l10n.noProjectsFound)),
                )
              else
                ...controller.projects.map((p) => _ProjectTile(project: p)),
            ],
          ),
        );
      }),
    );
  }
}

class _BannerCarousel extends StatelessWidget {
  const _BannerCarousel({required this.controller});

  final HomeController controller;

  @override
  Widget build(BuildContext context) {
    return CarouselSlider(
      options: CarouselOptions(
        height: 160,
        autoPlay: controller.banners.length > 1,
        viewportFraction: 0.92,
        enlargeCenterPage: true,
      ),
      items: controller.banners.map((banner) {
        return ClipRRect(
          borderRadius: BorderRadius.circular(12),
          child: banner.imageUrl == null
              ? Container(color: Theme.of(context).colorScheme.surfaceContainerHighest)
              : CachedNetworkImage(
                  imageUrl: banner.imageUrl!,
                  fit: BoxFit.cover,
                  width: double.infinity,
                  errorWidget: (_, _, _) => const Icon(Icons.image_not_supported),
                ),
        );
      }).toList(),
    );
  }
}

class _ProjectTile extends StatelessWidget {
  const _ProjectTile({required this.project});

  final Project project;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
      clipBehavior: Clip.antiAlias,
      child: ListTile(
        leading: SizedBox(
          width: 56,
          height: 56,
          child: project.logoUrl == null
              ? const Icon(Icons.apartment)
              : ClipRRect(
                  borderRadius: BorderRadius.circular(8),
                  child: CachedNetworkImage(
                    imageUrl: project.logoUrl!,
                    fit: BoxFit.cover,
                    errorWidget: (_, _, _) => const Icon(Icons.apartment),
                  ),
                ),
        ),
        title: Text(project.displayName),
        subtitle: project.shortCode != null ? Text(project.shortCode!) : null,
        trailing: const Icon(Icons.chevron_right),
        onTap: () => Get.to(
          () => const ProjectDetailView(),
          binding: BindingsBuilder(() {
            Get.put(ProjectDetailController(project));
          }),
        ),
      ),
    );
  }
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});

  final String message;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 12),
            ElevatedButton(onPressed: onRetry, child: Text(AppLocalizations.of(context)!.retry)),
          ],
        ),
      ),
    );
  }
}

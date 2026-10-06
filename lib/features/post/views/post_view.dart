import 'package:flutter/material.dart';

import 'package:get/get.dart';

import '../../../core/widgets/app_ui.dart';
import '../../../l10n/app_localizations.dart';
import '../controllers/post_controller.dart';
import 'post_detail_view.dart';

class PostView extends GetView<PostController> {
  const PostView({super.key});

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    return AppPage(
      appBar: AppBar(title: Text(l10n.announcements)),
      body: Obx(() {
        if (controller.isLoading.value && controller.posts.isEmpty) {
          return const Center(child: CircularProgressIndicator());
        }
        if (controller.errorMessage.value != null && controller.posts.isEmpty) {
          return AppEmptyState(message: controller.errorMessage.value!);
        }
        if (controller.posts.isEmpty) {
          return AppEmptyState(message: l10n.noPostsFound);
        }
        return RefreshIndicator(
          onRefresh: controller.load,
          child: ListView.separated(
            padding: const EdgeInsets.all(20),
            itemCount: controller.posts.length,
            separatorBuilder: (_, _) => const SizedBox(height: 12),
            itemBuilder: (context, index) {
              final post = controller.posts[index];
              return Card(
                clipBehavior: Clip.antiAlias,
                child: InkWell(
                  onTap: () => Get.to(() => PostDetailView(postId: post.id)),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      if (post.thumbnailUrl != null)
                        AppImage(url: post.thumbnailUrl!, height: 160),
                      Padding(
                        padding: const EdgeInsets.all(20),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              post.title ?? '',
                              style: Theme.of(context).textTheme.titleMedium,
                            ),
                            if (post.excerpt != null) ...[
                              const SizedBox(height: 4),
                              Text(post.excerpt!),
                            ],
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              );
            },
          ),
        );
      }),
    );
  }
}

import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../l10n/app_localizations.dart';
import '../controllers/post_controller.dart';
import 'post_detail_view.dart';

class PostView extends GetView<PostController> {
  const PostView({super.key});

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    return Scaffold(
      appBar: AppBar(title: Text(l10n.announcements)),
      body: Obx(() {
        if (controller.isLoading.value && controller.posts.isEmpty) {
          return const Center(child: CircularProgressIndicator());
        }
        if (controller.errorMessage.value != null && controller.posts.isEmpty) {
          return Center(child: Text(controller.errorMessage.value!));
        }
        if (controller.posts.isEmpty) {
          return Center(child: Text(l10n.noPostsFound));
        }
        return RefreshIndicator(
          onRefresh: controller.load,
          child: ListView.separated(
            padding: const EdgeInsets.all(12),
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
                      CachedNetworkImage(
                        imageUrl: post.thumbnailUrl!,
                        height: 160,
                        width: double.infinity,
                        fit: BoxFit.cover,
                        errorWidget: (_, _, _) => const SizedBox(height: 160),
                      ),
                    Padding(
                      padding: const EdgeInsets.all(12),
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

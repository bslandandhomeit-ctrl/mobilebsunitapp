import 'package:flutter/material.dart';

import 'package:get/get.dart';

import '../../../core/widgets/app_ui.dart';
import '../../../l10n/app_localizations.dart';
import '../controllers/notification_controller.dart';

class NotificationView extends GetView<NotificationController> {
  const NotificationView({super.key});

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    return AppPage(
      appBar: AppBar(title: Text(l10n.notifications)),
      body: Obx(() {
        if (controller.isLoading.value && controller.notifications.isEmpty) {
          return const Center(child: CircularProgressIndicator());
        }
        if (controller.errorMessage.value != null &&
            controller.notifications.isEmpty) {
          return AppEmptyState(message: controller.errorMessage.value!);
        }
        if (controller.notifications.isEmpty) {
          return AppEmptyState(message: l10n.noNotificationsFound);
        }
        return RefreshIndicator(
          onRefresh: controller.load,
          child: ListView.separated(
            padding: const EdgeInsets.fromLTRB(20, 8, 20, 28),
            itemCount: controller.notifications.length,
            separatorBuilder: (_, _) => const SizedBox(height: 12),
            itemBuilder: (context, index) {
              final item = controller.notifications[index];
              return Card(
                child: ListTile(
                  leading: Icon(
                    item.isRead
                        ? Icons.notifications_none
                        : Icons.notifications_active,
                    color: item.isRead
                        ? null
                        : Theme.of(context).colorScheme.primary,
                  ),
                  title: Text(item.title ?? l10n.notification),
                  subtitle: item.body != null ? Text(item.body!) : null,
                  onTap: () => controller.markAsRead(item),
                ),
              );
            },
          ),
        );
      }),
    );
  }
}

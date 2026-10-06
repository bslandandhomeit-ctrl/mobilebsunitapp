import 'package:flutter/material.dart';

import 'package:get/get.dart';

import '../../../core/widgets/reason_dialog.dart';
import '../../../core/widgets/app_ui.dart';
import '../../../l10n/app_localizations.dart';
import '../../auth/controllers/auth_controller.dart';
import '../controllers/hold_controller.dart';

class HoldView extends GetView<HoldController> {
  const HoldView({super.key});

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final canApprove =
        Get.find<AuthController>().currentUser.value?.canApproveHold ?? false;
    return AppPage(
      appBar: AppBar(title: Text(l10n.holdRequests)),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 8, 20, 16),
            child: TextField(
              onChanged: (v) => controller.searchQuery.value = v,
              decoration: InputDecoration(
                hintText: l10n.searchByUnitCode,
                prefixIcon: const Icon(Icons.search),

                isDense: true,
              ),
            ),
          ),
          Expanded(
            child: Obx(() {
              if (controller.isLoading.value && controller.requests.isEmpty) {
                return const Center(child: CircularProgressIndicator());
              }
              if (controller.errorMessage.value != null &&
                  controller.requests.isEmpty) {
                return AppEmptyState(message: controller.errorMessage.value!);
              }
              final items = controller.filteredRequests;
              if (items.isEmpty) {
                return AppEmptyState(message: l10n.noHoldRequestsFound);
              }
              return RefreshIndicator(
                onRefresh: controller.load,
                child: ListView.separated(
                  padding: const EdgeInsets.fromLTRB(20, 0, 20, 28),
                  physics: const AlwaysScrollableScrollPhysics(),
                  itemCount: items.length,
                  separatorBuilder: (_, _) => const SizedBox(height: 12),
                  itemBuilder: (context, index) {
                    final r = items[index];
                    final status = r.status?.toUpperCase();
                    Widget? trailing;
                    // Verified against the backend: approve/reject only
                    // apply while PENDING (unit_controller only), cancel
                    // only applies once APPROVED.
                    if (status == 'PENDING' && canApprove) {
                      trailing = Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          IconButton(
                            icon: const Icon(Icons.check, color: Colors.green),
                            tooltip: l10n.approve,
                            onPressed: () => controller.approve(r.id),
                          ),
                          IconButton(
                            icon: const Icon(Icons.close, color: Colors.red),
                            tooltip: l10n.reject,
                            onPressed: () => controller.reject(r.id),
                          ),
                        ],
                      );
                    } else if (status == 'APPROVED') {
                      trailing = TextButton(
                        onPressed: () async {
                          final reason = await promptForReason(
                            context,
                            title: l10n.cancelHoldRequest,
                          );
                          if (reason != null) {
                            controller.cancel(
                              r.id,
                              reason: reason.isEmpty ? null : reason,
                            );
                          }
                        },
                        child: Text(l10n.cancel),
                      );
                    }
                    return AppRequestCard(
                      title:
                          r.unitCode ?? l10n.unitNumber('${r.unitId ?? '-'}'),
                      status: r.status ?? l10n.unknownStatus,
                      actions: trailing,
                    );
                  },
                ),
              );
            }),
          ),
        ],
      ),
    );
  }
}

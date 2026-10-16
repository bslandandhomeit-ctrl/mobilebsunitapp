import 'package:flutter/material.dart';

import 'package:get/get.dart';

import '../../../core/widgets/reason_dialog.dart';
import '../../../core/widgets/app_ui.dart';
import '../../../l10n/app_localizations.dart';
import '../../requests/views/request_detail_view.dart';
import '../../auth/controllers/auth_controller.dart';
import '../../contract_request/views/contract_request_form_view.dart';
import '../controllers/deposit_controller.dart';
import '../models/unit_deposit_request.dart';
import 'change_unit_deposit_view.dart';

class DepositView extends GetView<DepositController> {
  const DepositView({super.key});

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final user = Get.find<AuthController>().currentUser.value;
    final canApprove = user?.canApproveDeposit ?? false;
    final canCancel = user?.canCancelDeposit ?? false;
    bool isOwner(UnitDepositRequest r) => user != null && r.isOwnedBy(user.id);
    return AppPage(
      appBar: AppBar(title: Text(l10n.depositRequests)),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 8, 20, 16),
            child: TextField(
              onChanged: controller.search,
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
                return AppEmptyState(
                  message: controller.errorMessage.value!,
                  action: TextButton(
                    onPressed: controller.load,
                    child: Text(l10n.retry),
                  ),
                );
              }
              final items = controller.filteredRequests;
              if (items.isEmpty) {
                return AppEmptyState(
                  message: l10n.noDepositRequestsFound,
                  action: TextButton(
                    onPressed: controller.load,
                    child: Text(l10n.retry),
                  ),
                );
              }
              return RefreshIndicator(
                onRefresh: controller.load,
                child: ListView.separated(
                  padding: const EdgeInsets.fromLTRB(20, 0, 20, 28),
                  physics: const AlwaysScrollableScrollPhysics(),
                  itemCount: items.length + (controller.hasMore.value ? 1 : 0),
                  separatorBuilder: (_, _) => const SizedBox(height: 12),
                  itemBuilder: (context, index) {
                    if (index == items.length) {
                      return Center(
                        child: controller.isLoadingMore.value
                            ? const CircularProgressIndicator()
                            : TextButton(
                                onPressed: controller.loadMore,
                                child: Text(l10n.loadMore),
                              ),
                      );
                    }
                    final r = items[index];
                    return AppRequestCard(
                      onTap: () => Get.to(
                        () => RequestDetailView(id: r.id, hold: false),
                      ),
                      summary: RequestSummary(data: r.details, hold: false),
                      title:
                          r.unitCode ?? l10n.unitNumber('${r.unitId ?? '-'}'),
                      status: r.status ?? l10n.unknownStatus,
                      actions: _actionsFor(
                        context,
                        l10n,
                        r,
                        canApprove,
                        canCancel,
                        isOwner(r),
                      ),
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

  Widget? _actionsFor(
    BuildContext context,
    AppLocalizations l10n,
    UnitDepositRequest r,
    bool canApprove,
    bool canCancel,
    bool isOwner,
  ) {
    final status = r.status?.toUpperCase();
    final actions = <Widget>[];

    // Verified against the backend: approve/reject only while PENDING
    // (unit_controller or sale_manager). Cancel/"request contract"/
    // "change unit" only while APPROVED or OVERDUE — cancel is
    // unit_controller only, the other two are owner-only.
    if (status == 'PENDING' && canApprove) {
      actions.addAll([
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
      ]);
    }
    if ((status == 'APPROVED' || status == 'OVERDUE') && canCancel) {
      actions.add(
        IconButton(
          icon: const Icon(Icons.cancel_outlined),
          tooltip: l10n.cancel,
          onPressed: () async {
            final reason = await promptForReason(
              context,
              title: l10n.cancelDepositRequest,
              required: true,
            );
            if (reason != null && reason.isNotEmpty) {
              controller.cancel(r.id, reason: reason);
            }
          },
        ),
      );
    }
    if ((status == 'APPROVED' || status == 'OVERDUE') && isOwner) {
      actions.add(
        IconButton(
          icon: const Icon(Icons.swap_horiz),
          tooltip: l10n.changeUnitTerms,
          onPressed: () =>
              Get.to(() => ChangeUnitDepositView(depositRequest: r)),
        ),
      );
    }
    if (status == 'APPROVED' && isOwner) {
      actions.add(
        TextButton(
          onPressed: () =>
              Get.to(() => ContractRequestFormView(depositRequest: r)),
          child: Text(l10n.contract),
        ),
      );
    }

    if (actions.isEmpty) return null;
    return Wrap(
      spacing: 4,
      runSpacing: 8,
      alignment: WrapAlignment.end,
      children: actions,
    );
  }
}

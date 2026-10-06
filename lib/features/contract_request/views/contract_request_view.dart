import 'package:flutter/material.dart';

import 'package:get/get.dart';

import '../../../core/widgets/reason_dialog.dart';
import '../../../core/widgets/app_ui.dart';
import '../../../l10n/app_localizations.dart';
import '../controllers/contract_request_controller.dart';
import 'contract_pdf_view.dart';

class ContractRequestView extends GetView<ContractRequestController> {
  const ContractRequestView({super.key});

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    return AppPage(
      appBar: AppBar(title: Text(l10n.contractRequests)),
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
                return AppEmptyState(message: l10n.noContractRequestsFound);
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
                    final isPending = r.status?.toUpperCase() == 'PENDING';
                    return AppRequestCard(
                      title:
                          r.unitCode ?? l10n.unitNumber('${r.unitId ?? '-'}'),
                      status: r.status ?? l10n.unknownStatus,
                      actions: r.contractId != null
                          ? TextButton(
                              onPressed: () => Get.to(
                                () =>
                                    ContractPdfView(contractId: r.contractId!),
                              ),
                              child: Text(l10n.viewContract),
                            )
                          : isPending
                          ? TextButton(
                              onPressed: () async {
                                final reason = await promptForReason(
                                  context,
                                  title: l10n.cancelContractRequest,
                                  required: true,
                                );
                                if (reason != null && reason.isNotEmpty) {
                                  controller.cancel(r.id, reason: reason);
                                }
                              },
                              child: Text(l10n.cancel),
                            )
                          : null,
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

import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../core/widgets/reason_dialog.dart';
import '../../../l10n/app_localizations.dart';
import '../controllers/contract_request_controller.dart';
import 'contract_pdf_view.dart';

class ContractRequestView extends GetView<ContractRequestController> {
  const ContractRequestView({super.key});

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    return Scaffold(
      appBar: AppBar(title: Text(l10n.contractRequests)),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(12),
            child: TextField(
              onChanged: (v) => controller.searchQuery.value = v,
              decoration: InputDecoration(
                hintText: l10n.searchByUnitCode,
                prefixIcon: const Icon(Icons.search),
                border: const OutlineInputBorder(),
                isDense: true,
              ),
            ),
          ),
          Expanded(
            child: Obx(() {
              if (controller.isLoading.value && controller.requests.isEmpty) {
                return const Center(child: CircularProgressIndicator());
              }
              if (controller.errorMessage.value != null && controller.requests.isEmpty) {
                return Center(child: Text(controller.errorMessage.value!));
              }
              final items = controller.filteredRequests;
              if (items.isEmpty) {
                return Center(child: Text(l10n.noContractRequestsFound));
              }
              return RefreshIndicator(
                onRefresh: controller.load,
                child: ListView.separated(
                  itemCount: items.length,
                  separatorBuilder: (_, _) => const Divider(height: 1),
                  itemBuilder: (context, index) {
                    final r = items[index];
                    final isPending = r.status?.toUpperCase() == 'PENDING';
                    return ListTile(
                      title: Text(r.unitCode ?? l10n.unitNumber('${r.unitId ?? '-'}')),
                      subtitle: Text(r.status ?? l10n.unknownStatus),
                      trailing: r.contractId != null
                          ? TextButton(
                              onPressed: () => Get.to(() => ContractPdfView(contractId: r.contractId!)),
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

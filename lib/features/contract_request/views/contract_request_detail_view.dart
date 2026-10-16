import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../core/constants/api_constants.dart';
import '../../../core/widgets/app_ui.dart';
import '../../../l10n/app_localizations.dart';
import '../../projects/models/unit.dart';
import '../../projects/views/unit_information_card.dart';
import '../../requests/views/request_detail_view.dart';
import '../data/contract_request_repository.dart';
import '../models/unit_contract_request.dart';
import 'contract_pdf_view.dart';

String contractAttachmentUrl(dynamic value) {
  final path = value?.toString() ?? '';
  final uri = Uri.tryParse(path);
  if (uri != null && (uri.scheme == 'http' || uri.scheme == 'https')) {
    return path;
  }
  final relative = path.replaceFirst(RegExp(r'^/+'), '');
  return Uri.parse(ApiConstants.baseUrl)
      .resolve(relative.startsWith('storage/') ? relative : 'storage/$relative')
      .toString();
}

class ContractRequestDetailView extends StatefulWidget {
  const ContractRequestDetailView({super.key, required this.id});
  final int id;
  @override
  State<ContractRequestDetailView> createState() =>
      _ContractRequestDetailViewState();
}

class _ContractRequestDetailViewState extends State<ContractRequestDetailView> {
  UnitContractRequest? _request;
  String? _error;
  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _error = null);
    try {
      final request = await ContractRequestRepository().get(widget.id);
      if (mounted) setState(() => _request = request);
    } catch (e) {
      if (mounted) setState(() => _error = e.toString());
    }
  }

  Widget _section(String title, List<(String, String)> rows) => AppPanel(
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        AppSectionTitle(title),
        for (final row in rows)
          Container(
            margin: const EdgeInsets.only(bottom: 10),
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: Theme.of(context).colorScheme.surfaceContainerLow,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(row.$1, style: Theme.of(context).textTheme.labelMedium),
                const SizedBox(height: 4),
                Text(
                  row.$2,
                  style: const TextStyle(fontWeight: FontWeight.w600),
                ),
              ],
            ),
          ),
      ],
    ),
  );
  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context)!;
    final theme = Theme.of(context);
    final r = _request;
    final d = r?.details ?? {};
    final creator = d['created_by'] as Map<String, dynamic>? ?? {};
    final deposit = d['unit_deposit_request'] as Map<String, dynamic>? ?? {};
    final attachments = (d['attachments'] as List? ?? [])
        .whereType<Map<String, dynamic>>()
        .toList();
    String gender(dynamic v) => '$v' == '1'
        ? l.male
        : '$v' == '2'
        ? l.female
        : 'N/A';
    return AppPage(
      appBar: AppBar(title: Text(l.contractRequests)),
      body: _error != null
          ? AppEmptyState(
              message: _error!,
              action: TextButton(onPressed: _load, child: Text(l.retry)),
            )
          : r == null
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(20),
                children: [
                  Container(
                    padding: const EdgeInsets.all(22),
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(22),
                      gradient: LinearGradient(
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                        colors: [
                          theme.colorScheme.primary,
                          const Color(0xFF175387),
                        ],
                      ),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            const Icon(
                              Icons.description_rounded,
                              color: Colors.white70,
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                l.contractRequests,
                                style: theme.textTheme.labelLarge?.copyWith(
                                  color: Colors.white70,
                                ),
                              ),
                            ),
                            Text(
                              '#${r.id}',
                              style: const TextStyle(color: Colors.white70),
                            ),
                          ],
                        ),
                        const SizedBox(height: 16),
                        Text(
                          r.unitCode ?? 'N/A',
                          style: theme.textTheme.headlineSmall?.copyWith(
                            color: Colors.white,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        const SizedBox(height: 12),
                        AppStatusBadge(r.status ?? l.unknownStatus),
                      ],
                    ),
                  ),
                  const SizedBox(height: 16),
                  _section(l.requestedByLabel, [
                    (l.requestedByLabel, requestValue(creator['name'])),
                    (l.customerPhone, requestValue(creator['phone_number'])),
                    (l.requestDateLabel, requestDate(d['created_at'])),
                    (l.signedDate, requestDate(d['signed_at'])),
                    (l.firstPaymentDate, requestDate(d['start_payment_date'])),
                  ]),
                  const SizedBox(height: 16),
                  _section(l.customerInformationTitle, [
                    (
                      l.customerOneNameLabel,
                      requestValue(deposit['customer_name']),
                    ),
                    (
                      l.customerOneGenderLabel,
                      gender(deposit['customer_gender']),
                    ),
                    (
                      l.customerTwoNameLabel,
                      requestValue(deposit['customer2_name']),
                    ),
                    (
                      l.customerTwoGenderLabel,
                      gender(deposit['customer2_gender']),
                    ),
                    (
                      l.phoneLineOneLabel,
                      requestValue(deposit['customer_phone_number']),
                    ),
                    (
                      l.phoneLineTwoLabel,
                      requestValue(deposit['customer_phone_number2']),
                    ),
                  ]),
                  const SizedBox(height: 16),
                  if (d['unit'] is Map<String, dynamic>)
                    UnitInformationCard(
                      unit: Unit.fromJson(d['unit']),
                      showFeatures: true,
                    ),
                  const SizedBox(height: 16),
                  AppPanel(child: requestAmountTiles(context, deposit)),
                  const SizedBox(height: 16),
                  _section(l.remark, [(l.remark, requestValue(d['remark']))]),
                  const SizedBox(height: 16),
                  AppPanel(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        AppSectionTitle(l.requestAttachmentsTitle),
                        Text(
                          l.requestAttachmentsNote,
                          style: theme.textTheme.bodySmall,
                        ),
                        const SizedBox(height: 12),
                        if (attachments.isEmpty) Text(l.noneLabel),
                        for (final attachment in attachments)
                          Padding(
                            padding: const EdgeInsets.only(bottom: 12),
                            child: ClipRRect(
                              borderRadius: BorderRadius.circular(12),
                              child: AppImage(
                                url: contractAttachmentUrl(attachment['path']),
                                height: 220,
                                fit: BoxFit.contain,
                              ),
                            ),
                          ),
                      ],
                    ),
                  ),
                  if (r.contractId != null) ...[
                    const SizedBox(height: 16),
                    FilledButton.icon(
                      onPressed: () => Get.to(
                        () => ContractPdfView(contractId: r.contractId!),
                      ),
                      icon: const Icon(Icons.picture_as_pdf_outlined),
                      label: Text(l.viewContract),
                    ),
                  ],
                ],
              ),
            ),
    );
  }
}

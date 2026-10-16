import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../../core/network/api_client.dart';
import '../../../core/models/paginated_response.dart';
import '../../../core/widgets/app_ui.dart';
import '../../../l10n/app_localizations.dart';
import '../../projects/models/unit.dart';
import '../../projects/views/unit_information_card.dart';

String requestDate(dynamic value) {
  final date = DateTime.tryParse(value?.toString() ?? '');
  return date == null
      ? 'N/A'
      : DateFormat('dd MMM yyyy, h:mm a').format(date.toLocal());
}

String requestValue(dynamic value) =>
    value == null || '$value'.trim().isEmpty ? 'N/A' : '$value';
String requestMoney(dynamic value) {
  final amount = double.tryParse('$value');
  return amount == null ? 'N/A' : formatUsd(amount);
}

Widget requestAmountTiles(BuildContext context, Map<String, dynamic> data) {
  final l = AppLocalizations.of(context)!;
  return LayoutBuilder(
    builder: (context, constraints) => Wrap(
      spacing: 10,
      runSpacing: 10,
      children: [
        for (final entry in [
          (l.depositAmount, data['deposit_amount'], Icons.payments_rounded),
          (
            l.receivedAmountLabel,
            data['receiving_amount'],
            Icons.account_balance_wallet_outlined,
          ),
        ])
          Container(
            width: constraints.maxWidth < 240
                ? constraints.maxWidth
                : (constraints.maxWidth - 10) / 2,
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: Theme.of(context).colorScheme.surfaceContainerLow,
              borderRadius: BorderRadius.circular(14),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(
                  entry.$3,
                  size: 20,
                  color: Theme.of(context).colorScheme.primary,
                ),
                const SizedBox(height: 8),
                Text(entry.$1, style: Theme.of(context).textTheme.labelSmall),
                const SizedBox(height: 4),
                Text(
                  requestMoney(entry.$2),
                  style: Theme.of(context).textTheme.titleMedium
                      ?.copyWith(fontWeight: FontWeight.w700),
                ),
              ],
            ),
          ),
      ],
    ),
  );
}

Widget requestApproval(BuildContext context, String label, dynamic status) =>
    Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Wrap(
        spacing: 10,
        runSpacing: 6,
        crossAxisAlignment: WrapCrossAlignment.center,
        children: [
          Text(label, style: Theme.of(context).textTheme.labelMedium),
          AppStatusBadge(requestValue(status)),
        ],
      ),
    );

class RequestSummary extends StatelessWidget {
  const RequestSummary({
    super.key,
    required this.data,
    required this.hold,
    this.showApprovals = true,
  });
  final Map<String, dynamic> data;
  final bool hold;
  final bool showApprovals;
  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context)!;
    final creator = data['created_by'] as Map<String, dynamic>? ?? {};
    final unit = data['unit'] as Map<String, dynamic>? ?? {};
    final type = unit['unit_type'] as Map<String, dynamic>? ?? {};
    final project = type['project'] as Map<String, dynamic>? ?? {};
    String name(Map<String, dynamic> d) =>
        requestValue(d['name_en'] ?? d['name']);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            CircleAvatar(
              radius: 18,
              child: Text(requestValue(creator['name']).substring(0, 1)),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    requestValue(creator['name']),
                    style: const TextStyle(fontWeight: FontWeight.w600),
                  ),
                  Text(
                    requestDate(data['created_at']),
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ],
              ),
            ),
            const Icon(Icons.chevron_right_rounded, size: 20),
          ],
        ),
        const SizedBox(height: 12),
        if (!hold)
          Text(
            requestValue(data['customer_name']),
            style: const TextStyle(fontWeight: FontWeight.w600),
          ),
        Text(
          '${name(type)} · ${name(project)}',
          style: Theme.of(context).textTheme.bodySmall,
        ),
        const SizedBox(height: 8),
        if (hold)
          Text(
            '${l.holdDaysLabel}: ${requestValue(data['hold_day'])} · ${l.expirationDateLabel}: ${requestDate(data['release_date'])}',
            style: Theme.of(context).textTheme.bodySmall,
          )
        else ...[
          requestAmountTiles(context, data),
          const SizedBox(height: 10),
          if (showApprovals)
            requestApproval(
              context,
              l.salesManagerLabel,
              data['sale_manager_status'],
            ),
          if (showApprovals)
            requestApproval(
              context,
              l.unitControllerLabel,
              data['unit_controller_status'],
            ),
        ],
      ],
    );
  }
}

class RequestDetailView extends StatefulWidget {
  const RequestDetailView({super.key, required this.id, required this.hold});
  final int id;
  final bool hold;
  @override
  State<RequestDetailView> createState() => _RequestDetailViewState();
}

class _RequestDetailViewState extends State<RequestDetailView> {
  Map<String, dynamic>? _data;
  String? _error;
  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _error = null);
    try {
      final response = await ApiClient.instance.get<Map<String, dynamic>>(
        'api/unit_${widget.hold ? 'hold' : 'deposit'}_requests/${widget.id}',
        queryParameters: {
          'embed': widget.hold ? 'createdBy,unit.unitType.project' : 'createdBy,unit.unitType.project,saleManager,unitController,paymentOption',
        },
      );
      if (mounted) {
        setState(() => _data = PaginatedResponse.unwrap(response.data));
      }
    } catch (e) {
      if (mounted) setState(() => _error = e.toString());
    }
  }

  Widget _panel(String title, List<(String, String)> values) => AppPanel(
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        AppSectionTitle(title),
        for (final v in values)
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
                Text(v.$1, style: Theme.of(context).textTheme.labelMedium),
                const SizedBox(height: 4),
                Text(v.$2, style: const TextStyle(fontWeight: FontWeight.w600)),
              ],
            ),
          ),
      ],
    ),
  );
  Widget _depositOverview(Map<String, dynamic> d, AppLocalizations l) {
    final theme = Theme.of(context);
    final unit = d['unit'] as Map<String, dynamic>? ?? {};
    final creator = d['created_by'] as Map<String, dynamic>? ?? {};
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Container(
          padding: const EdgeInsets.all(22),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(22),
            gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [theme.colorScheme.primary, const Color(0xFF175387)],
            ),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  const Icon(Icons.payments_rounded, color: Colors.white70),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      l.depositRequestTitle,
                      style: theme.textTheme.labelLarge?.copyWith(
                        color: Colors.white70,
                      ),
                    ),
                  ),
                  Text(
                    '#${d['id']}',
                    style: const TextStyle(color: Colors.white70),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              Text(
                requestValue(unit['code']),
                style: theme.textTheme.headlineSmall?.copyWith(
                  color: Colors.white,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 12),
              AppStatusBadge(requestValue(d['status'])),
            ],
          ),
        ),
        const SizedBox(height: 16),
        AppPanel(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              requestAmountTiles(context, d),
              const SizedBox(height: 18),
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  CircleAvatar(
                    radius: 21,
                    child: Text(requestValue(creator['name']).substring(0, 1)),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          l.requestedByLabel,
                          style: theme.textTheme.labelSmall,
                        ),
                        Text(
                          requestValue(creator['name']),
                          style: theme.textTheme.titleSmall,
                        ),
                        Text(
                          requestValue(creator['phone_number']),
                          style: theme.textTheme.bodySmall,
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const Divider(height: 28),
              Text(l.requestDateLabel, style: theme.textTheme.labelMedium),
              const SizedBox(height: 4),
              Text(
                requestDate(d['created_at']),
                style: theme.textTheme.bodyMedium,
              ),
              const SizedBox(height: 12),
              requestApproval(
                context,
                l.salesManagerLabel,
                d['sale_manager_status'],
              ),
              requestApproval(
                context,
                l.unitControllerLabel,
                d['unit_controller_status'],
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _holdOverview(Map<String, dynamic> d, AppLocalizations l) {
    final theme = Theme.of(context);
    final creator = d['created_by'] as Map<String, dynamic>? ?? {};
    final unit = d['unit'] as Map<String, dynamic>? ?? {};
    Widget dateRow(IconData icon, String label, String value) => Padding(
      padding: const EdgeInsets.symmetric(vertical: 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: theme.colorScheme.primary.withValues(alpha: .07),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, size: 20, color: theme.colorScheme.primary),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label, style: theme.textTheme.labelMedium),
                const SizedBox(height: 4),
                Text(
                  value,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Container(
          padding: const EdgeInsets.all(22),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(22),
            gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [theme.colorScheme.primary, const Color(0xFF175387)],
            ),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  const Icon(
                    Icons.lock_clock_rounded,
                    color: Colors.white70,
                    size: 22,
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      l.holdRequestTitle,
                      style: theme.textTheme.labelLarge?.copyWith(
                        color: Colors.white70,
                      ),
                    ),
                  ),
                  Text(
                    '#${d['id']}',
                    style: const TextStyle(color: Colors.white70),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              Text(
                requestValue(unit['code']),
                style: theme.textTheme.headlineSmall?.copyWith(
                  color: Colors.white,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 12),
              AppStatusBadge(requestValue(d['status'])),
            ],
          ),
        ),
        const SizedBox(height: 16),
        AppPanel(
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              CircleAvatar(
                radius: 24,
                backgroundColor: theme.colorScheme.primaryContainer,
                child: Text(
                  requestValue(creator['name']).substring(0, 1),
                  style: theme.textTheme.titleLarge,
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      l.requestedByLabel,
                      style: theme.textTheme.labelMedium,
                    ),
                    const SizedBox(height: 4),
                    Text(
                      requestValue(creator['name']),
                      style: theme.textTheme.titleMedium,
                    ),
                    const SizedBox(height: 6),
                    Text(
                      requestValue(creator['phone_number']),
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: theme.colorScheme.onSurfaceVariant,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        AppPanel(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      l.holdDateLabel,
                      style: theme.textTheme.titleMedium,
                    ),
                  ),
                  Chip(
                    avatar: const Icon(Icons.schedule_rounded, size: 16),
                    label: Text(
                      '${requestValue(d['hold_day'])} ${l.holdDaysLabel}',
                    ),
                  ),
                ],
              ),
              dateRow(
                Icons.event_available_rounded,
                l.holdDateLabel,
                requestDate(d['created_at']),
              ),
              const Divider(height: 12),
              dateRow(
                Icons.event_busy_rounded,
                l.expirationDateLabel,
                requestDate(d['release_date']),
              ),
            ],
          ),
        ),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context)!;
    final d = _data;
    String gender(dynamic v) => '$v' == '1'
        ? l.male
        : '$v' == '2'
        ? l.female
        : 'N/A';
    return AppPage(
      appBar: AppBar(
        title: Text(widget.hold ? l.holdRequestTitle : l.depositRequestTitle),
      ),
      body: _error != null
          ? AppEmptyState(
              message: _error!,
              action: TextButton(onPressed: _load, child: Text(l.retry)),
            )
          : d == null
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(20),
                children: [
                  if (widget.hold)
                    _holdOverview(d, l)
                  else
                    _depositOverview(d, l),
                  const SizedBox(height: 16),
                  if (!widget.hold) ...[
                    _panel(l.customerInformationTitle, [
                      (
                        l.customerOneNameLabel,
                        requestValue(d['customer_name']),
                      ),
                      (l.customerOneGenderLabel, gender(d['customer_gender'])),
                      (
                        l.customerTwoNameLabel,
                        requestValue(d['customer2_name']),
                      ),
                      (l.customerTwoGenderLabel, gender(d['customer2_gender'])),
                      (
                        l.phoneLineOneLabel,
                        requestValue(d['customer_phone_number']),
                      ),
                      (
                        l.phoneLineTwoLabel,
                        requestValue(d['customer_phone_number2']),
                      ),
                    ]),
                    const SizedBox(height: 16),
                  ],
                  if (d['unit'] is Map<String, dynamic>)
                    UnitInformationCard(
                      unit: Unit.fromJson(d['unit']),
                      showFeatures: true,
                    ),
                  const SizedBox(height: 16),
                  if (!widget.hold) ...[
                    const SizedBox(height: 16),
                    _panel(l.otherDiscountDepositTitle, [
                      (l.unitPriceLabel, requestMoney(d['unit_sale_price'])),
                      (l.depositAmount, requestMoney(d['deposit_amount'])),
                      (
                        l.receivedAmountLabel,
                        requestMoney(d['receiving_amount']),
                      ),
                      (
                        l.otherDiscountAllowance,
                        requestMoney(d['other_discount_allowance']),
                      ),
                      (
                        l.promotionalDiscountLabel,
                        requestMoney(d['discount_promotion']),
                      ),
                      (
                        l.salesManagerLabel,
                        requestValue(d['sale_manager_status']),
                      ),
                      (
                        l.unitControllerLabel,
                        requestValue(d['unit_controller_status']),
                      ),
                    ]),
                  ],
                  const SizedBox(height: 16),
                  AppPanel(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        AppSectionTitle(l.remark),
                        Text(
                          requestValue(d['remark']),
                          style: Theme.of(context).textTheme.bodyMedium,
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

import 'package:flutter/material.dart';

import '../../../core/widgets/app_ui.dart';
import '../../../l10n/app_localizations.dart';
import '../data/payment_option_repository.dart';
import '../models/payment_option.dart';
import '../models/unit_type.dart';

class UnitTypeDetailView extends StatefulWidget {
  const UnitTypeDetailView({super.key, required this.unitType});

  final UnitType unitType;

  @override
  State<UnitTypeDetailView> createState() => _UnitTypeDetailViewState();
}

class _UnitTypeDetailViewState extends State<UnitTypeDetailView> {
  final _repository = PaymentOptionRepository();
  List<PaymentOption> _options = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      _options = (await _repository.getByUnitTypeId(widget.unitType.id)).items;
    } catch (_) {
      // Non-fatal — just show an empty list.
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    return AppPage(
      appBar: AppBar(title: Text(widget.unitType.displayName)),
      body: ListView(
        children: [
          if (widget.unitType.featureImageUrl != null)
            AppImage(url: widget.unitType.featureImageUrl!, height: 200),
          if (widget.unitType.category != null)
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 12, 20, 32),
              child: Chip(label: Text(widget.unitType.category!)),
            ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
            child: Text(
              l10n.paymentOptions,
              style: Theme.of(context).textTheme.titleLarge,
            ),
          ),
          if (_isLoading)
            const Padding(
              padding: EdgeInsets.all(24),
              child: Center(child: CircularProgressIndicator()),
            )
          else if (_options.isEmpty)
            Padding(
              padding: const EdgeInsets.all(24),
              child: AppEmptyState(message: l10n.noPaymentOptionsForUnitType),
            )
          else
            ..._options.map(
              (o) => Card(
                margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
                child: ListTile(
                  title: Text(o.name),
                  subtitle: Text(
                    [
                      if (o.depositAmount != null)
                        l10n.depositAmountShort('\$${o.depositAmount}'),
                      if (o.loanDuration != null)
                        l10n.loanDurationYearsShort('${o.loanDuration}'),
                      if (o.interest != null)
                        l10n.interestPercentShort('${o.interest}'),
                    ].join(' · '),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

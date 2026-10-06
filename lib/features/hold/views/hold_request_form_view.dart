import 'package:flutter/material.dart';

import 'package:get/get.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/widgets/app_ui.dart';
import '../../../l10n/app_localizations.dart';
import '../../projects/models/unit.dart';
import '../data/hold_repository.dart';

class HoldRequestFormView extends StatefulWidget {
  const HoldRequestFormView({super.key, required this.unit});

  final Unit unit;

  @override
  State<HoldRequestFormView> createState() => _HoldRequestFormViewState();
}

class _HoldRequestFormViewState extends State<HoldRequestFormView> {
  final _repository = HoldRepository();
  int _holdDay = 3;
  bool _isSubmitting = false;
  String? _error;

  Future<void> _submit() async {
    setState(() {
      _isSubmitting = true;
      _error = null;
    });
    try {
      await _repository.create(unitId: widget.unit.id, holdDay: _holdDay);
      if (!mounted) return;
      final l10n = AppLocalizations.of(context)!;
      Get.back(result: true);
      Get.snackbar(
        l10n.holdRequested,
        l10n.holdRequestedMessage(widget.unit.name),
      );
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    return AppPage(
      appBar: AppBar(title: Text(l10n.requestHold)),
      body: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 32),
        child: AppPanel(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(l10n.unit, style: Theme.of(context).textTheme.labelMedium),
              Text(
                widget.unit.name,
                style: Theme.of(context).textTheme.titleLarge,
              ),
              const SizedBox(height: 8),
              Text(l10n.holdSubmitDescription),
              const SizedBox(height: 16),
              Text(l10n.holdDuration(_holdDay)),
              Slider(
                value: _holdDay.toDouble(),
                min: 1,
                max: 7,
                divisions: 6,
                label: '$_holdDay',
                onChanged: (v) => setState(() => _holdDay = v.round()),
              ),
              if (_error != null) ...[
                const SizedBox(height: 12),
                Text(_error!, style: const TextStyle(color: Colors.red)),
              ],
              const SizedBox(height: 24),
              ElevatedButton(
                onPressed: _isSubmitting ? null : _submit,
                child: _isSubmitting
                    ? const SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : Text(l10n.submitHoldRequest),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

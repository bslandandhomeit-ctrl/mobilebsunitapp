import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/widgets/app_ui.dart';
import '../../../l10n/app_localizations.dart';
import '../../projects/models/unit.dart';
import '../../projects/views/unit_information_card.dart';
import '../data/hold_repository.dart';

class HoldRequestFormView extends StatefulWidget {
  const HoldRequestFormView({super.key, required this.unit});
  final Unit unit;
  @override
  State<HoldRequestFormView> createState() => _HoldRequestFormViewState();
}

class _HoldRequestFormViewState extends State<HoldRequestFormView> {
  final _repository = HoldRepository();
  final _remark = TextEditingController();
  final _form = GlobalKey<FormState>();
  bool _saving = false;
  String? _error;
  @override
  void dispose() {
    _remark.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (!_form.currentState!.validate()) return;
    FocusManager.instance.primaryFocus?.unfocus();
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      // Retain the existing default: the API requires a duration of 1–7 days.
      await _repository.create(
        unitId: widget.unit.id,
        holdDay: 3,
        remark: _remark.text.trim(),
      );
      if (!mounted) return;
      final l = AppLocalizations.of(context)!;
      Get.back(result: true);
      Get.snackbar(l.holdRequested, l.holdRequestedMessage(widget.unit.name));
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context)!;
    return AppPage(
      appBar: AppBar(title: Text(l.requestHold)),
      body: Form(
        key: _form,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
          children: [
            UnitInformationCard(unit: widget.unit),
            const SizedBox(height: 20),
            AppPanel(
              child: TextFormField(
                controller: _remark,
                minLines: 4,
                maxLines: 6,
                maxLength: 500,
                decoration: InputDecoration(
                  labelText: l.remark,
                  hintText: l.remarkPlaceholder,
                ),
              ),
            ),
            if (_error != null)
              Padding(
                padding: const EdgeInsets.only(top: 16),
                child: Text(
                  _error!,
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
              ),
            const SizedBox(height: 24),
            FilledButton(
              onPressed: _saving ? null : _save,
              child: _saving
                  ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : Text(l.saveLabel),
            ),
          ],
        ),
      ),
    );
  }
}

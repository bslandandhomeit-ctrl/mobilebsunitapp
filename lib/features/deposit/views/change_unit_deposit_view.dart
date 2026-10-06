import 'package:flutter/material.dart';

import 'package:get/get.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/widgets/app_ui.dart';
import '../../../l10n/app_localizations.dart';
import '../../projects/data/payment_option_repository.dart';
import '../../projects/data/projects_repository.dart';
import '../../projects/models/payment_option.dart';
import '../../projects/models/unit.dart';
import '../data/deposit_repository.dart';
import '../models/unit_deposit_request.dart';

/// Verified against `Api/UnitDepositRequestController@changeUnit`
/// (2026-09-11): owner-only, only while APPROVED/OVERDUE. Lets the agent
/// either move this deposit to a different unit, or just adjust the
/// discount/payment terms on the same one.
class ChangeUnitDepositView extends StatefulWidget {
  const ChangeUnitDepositView({super.key, required this.depositRequest});

  final UnitDepositRequest depositRequest;

  @override
  State<ChangeUnitDepositView> createState() => _ChangeUnitDepositViewState();
}

class _ChangeUnitDepositViewState extends State<ChangeUnitDepositView> {
  final _repository = DepositRepository();
  final _projectsRepository = ProjectsRepository();
  final _paymentOptionRepository = PaymentOptionRepository();

  final _unitSearchController = TextEditingController();
  final _otherDiscountController = TextEditingController(text: '0');
  final _remarkController = TextEditingController();
  final _loanDurationController = TextEditingController();
  final _interestController = TextEditingController();
  final _specialDiscountController = TextEditingController();
  final _firstPaymentDurationController = TextEditingController();
  final _firstPaymentPercentageController = TextEditingController();

  Unit? _selectedUnit;
  List<Unit> _searchResults = [];
  List<PaymentOption> _paymentOptions = [];
  PaymentOption? _selectedPaymentOption;
  bool _isFirstPayment = false;
  bool _useCustomLoanTerms = false;
  bool _isSearching = false;
  bool _isSubmitting = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _otherDiscountController.text =
        (widget.depositRequest.otherDiscountAllowance ?? 0).toString();
  }

  Future<void> _searchUnits(String term) async {
    if (term.trim().isEmpty) {
      setState(() => _searchResults = []);
      return;
    }
    setState(() => _isSearching = true);
    try {
      _searchResults = (await _projectsRepository.searchUnits(term.trim()))
          .items;
    } catch (_) {
      // Ignore — keep last results.
    } finally {
      if (mounted) setState(() => _isSearching = false);
    }
  }

  Future<void> _selectUnit(Unit unit) async {
    setState(() {
      _selectedUnit = unit;
      _searchResults = [];
      _unitSearchController.text = unit.name;
      _paymentOptions = [];
      _selectedPaymentOption = null;
    });
    final unitTypeId = unit.unitTypeId;
    if (unitTypeId == null) return;
    try {
      _paymentOptions = (await _paymentOptionRepository.getByUnitTypeId(
        unitTypeId,
      )).items;
      if (mounted) setState(() {});
    } catch (_) {
      // Non-fatal — custom loan terms remain available.
    }
  }

  Future<void> _submit() async {
    final targetUnitId = _selectedUnit?.id ?? widget.depositRequest.unitId;
    if (targetUnitId == null) return;
    setState(() {
      _isSubmitting = true;
      _error = null;
    });
    try {
      await _repository.changeUnit(
        id: widget.depositRequest.id,
        unitId: targetUnitId,
        otherDiscountAllowance:
            double.tryParse(_otherDiscountController.text.trim()) ?? 0,
        remark: _remarkController.text.trim().isEmpty
            ? null
            : _remarkController.text.trim(),
        paymentOptionId: _useCustomLoanTerms
            ? null
            : _selectedPaymentOption?.id,
        loanDuration: _useCustomLoanTerms
            ? int.tryParse(_loanDurationController.text.trim())
            : null,
        interest: _useCustomLoanTerms
            ? double.tryParse(_interestController.text.trim())
            : null,
        specialDiscount: _useCustomLoanTerms
            ? double.tryParse(_specialDiscountController.text.trim())
            : null,
        isFirstPayment: _useCustomLoanTerms ? _isFirstPayment : null,
        firstPaymentDuration: _useCustomLoanTerms
            ? int.tryParse(_firstPaymentDurationController.text.trim())
            : null,
        firstPaymentPercentage: _useCustomLoanTerms
            ? double.tryParse(_firstPaymentPercentageController.text.trim())
            : null,
      );
      if (!mounted) return;
      final l10n = AppLocalizations.of(context)!;
      Get.back(result: true);
      Get.snackbar(l10n.updated, l10n.depositRequestUpdatedMessage);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  @override
  void dispose() {
    _unitSearchController.dispose();
    _otherDiscountController.dispose();
    _remarkController.dispose();
    _loanDurationController.dispose();
    _interestController.dispose();
    _specialDiscountController.dispose();
    _firstPaymentDurationController.dispose();
    _firstPaymentPercentageController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    return AppPage(
      appBar: AppBar(title: Text(l10n.changeUnitTermsTitle)),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 32),
        children: [
          Text(
            l10n.currentUnit,
            style: Theme.of(context).textTheme.labelMedium,
          ),
          Text(
            widget.depositRequest.unitCode ??
                l10n.unitNumber('${widget.depositRequest.unitId ?? '-'}'),
          ),
          const SizedBox(height: 16),
          TextField(
            controller: _unitSearchController,
            onChanged: _searchUnits,
            decoration: InputDecoration(
              labelText: l10n.searchDifferentUnitHint,

              suffixIcon: _isSearching
                  ? const Padding(
                      padding: EdgeInsets.all(12),
                      child: SizedBox(
                        width: 16,
                        height: 16,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      ),
                    )
                  : null,
            ),
          ),
          if (_searchResults.isNotEmpty)
            Card(
              margin: const EdgeInsets.only(top: 4),
              child: Column(
                children: _searchResults
                    .map(
                      (u) => ListTile(
                        title: Text(u.name),
                        trailing: Chip(label: Text(u.status ?? 'UNKNOWN')),
                        onTap: () => _selectUnit(u),
                      ),
                    )
                    .toList(),
              ),
            ),
          if (_selectedUnit != null)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Text(
                l10n.newUnitLabel(_selectedUnit!.name),
                style: Theme.of(context).textTheme.titleMedium,
              ),
            ),
          const SizedBox(height: 24),
          TextField(
            controller: _otherDiscountController,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: InputDecoration(labelText: l10n.otherDiscountAllowance),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _remarkController,
            decoration: InputDecoration(labelText: l10n.remark),
          ),
          const SizedBox(height: 24),
          Text(
            l10n.paymentOption,
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(height: 8),
          if (_paymentOptions.isNotEmpty)
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              title: Text(l10n.useCustomLoanTerms),
              value: _useCustomLoanTerms,
              onChanged: (v) => setState(() => _useCustomLoanTerms = v),
            ),
          if (!_useCustomLoanTerms && _paymentOptions.isNotEmpty)
            DropdownButtonFormField<PaymentOption>(
              isExpanded: true,
              initialValue: _selectedPaymentOption,
              decoration: InputDecoration(labelText: l10n.paymentOption),
              items: _paymentOptions
                  .map((o) => DropdownMenuItem(value: o, child: Text(o.name)))
                  .toList(),
              onChanged: (v) => setState(() => _selectedPaymentOption = v),
            )
          else ...[
            TextField(
              controller: _loanDurationController,
              keyboardType: TextInputType.number,
              decoration: InputDecoration(labelText: l10n.loanDurationYears),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _interestController,
              keyboardType: const TextInputType.numberWithOptions(
                decimal: true,
              ),
              decoration: InputDecoration(labelText: l10n.annualInterestRate),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _specialDiscountController,
              keyboardType: const TextInputType.numberWithOptions(
                decimal: true,
              ),
              decoration: InputDecoration(
                labelText: l10n.specialDiscountPercent,
              ),
            ),
            const SizedBox(height: 8),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              title: Text(l10n.hasFirstPayment),
              value: _isFirstPayment,
              onChanged: (v) => setState(() => _isFirstPayment = v),
            ),
            if (_isFirstPayment) ...[
              TextField(
                controller: _firstPaymentDurationController,
                keyboardType: TextInputType.number,
                decoration: InputDecoration(
                  labelText: l10n.firstPaymentDurationMonths,
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _firstPaymentPercentageController,
                keyboardType: const TextInputType.numberWithOptions(
                  decimal: true,
                ),
                decoration: InputDecoration(
                  labelText: l10n.firstPaymentPercentage,
                ),
              ),
            ],
          ],
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
                : Text(l10n.saveChanges),
          ),
        ],
      ),
    );
  }
}

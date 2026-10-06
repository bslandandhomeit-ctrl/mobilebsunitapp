import 'package:flutter/material.dart';

import 'package:get/get.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/widgets/app_ui.dart';
import '../../../l10n/app_localizations.dart';
import '../../projects/data/payment_option_repository.dart';
import '../../projects/models/payment_option.dart';
import '../../projects/models/unit.dart';
import '../data/deposit_repository.dart';

class DepositRequestFormView extends StatefulWidget {
  const DepositRequestFormView({super.key, required this.unit});

  final Unit unit;

  @override
  State<DepositRequestFormView> createState() => _DepositRequestFormViewState();
}

class _DepositRequestFormViewState extends State<DepositRequestFormView> {
  final _repository = DepositRepository();
  final _paymentOptionRepository = PaymentOptionRepository();

  final _depositAmountController = TextEditingController();
  final _otherDiscountController = TextEditingController(text: '0');
  final _customerNameController = TextEditingController();
  final _customerPhoneController = TextEditingController();
  final _customer2NameController = TextEditingController();
  final _customer2PhoneController = TextEditingController();
  final _remarkController = TextEditingController();

  // Custom loan term controllers (used when no payment option is selected).
  final _loanDurationController = TextEditingController();
  final _interestController = TextEditingController();
  final _specialDiscountController = TextEditingController();
  final _firstPaymentDurationController = TextEditingController();
  final _firstPaymentPercentageController = TextEditingController();

  int _customerGender = 1; // 1 = Male, 2 = Female
  int? _customer2Gender;
  bool _hasSecondCustomer = false;
  bool _isFirstPayment = false;
  bool _useCustomLoanTerms = false;
  PaymentOption? _selectedPaymentOption;

  List<PaymentOption> _paymentOptions = [];
  bool _loadingOptions = true;
  bool _isSubmitting = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadPaymentOptions();
  }

  Future<void> _loadPaymentOptions() async {
    final unitTypeId = widget.unit.unitTypeId;
    if (unitTypeId != null) {
      try {
        _paymentOptions = (await _paymentOptionRepository.getByUnitTypeId(
          unitTypeId,
        )).items;
      } catch (_) {
        // Non-fatal — the agent can still enter custom loan terms.
      }
    }
    if (mounted) {
      setState(() {
        _loadingOptions = false;
        _useCustomLoanTerms = _paymentOptions.isEmpty;
      });
    }
  }

  Future<void> _submit() async {
    setState(() {
      _isSubmitting = true;
      _error = null;
    });
    try {
      await _repository.create(
        unitId: widget.unit.id,
        depositAmount:
            double.tryParse(_depositAmountController.text.trim()) ?? 0,
        otherDiscountAllowance:
            double.tryParse(_otherDiscountController.text.trim()) ?? 0,
        customerName: _customerNameController.text.trim(),
        customerGender: _customerGender,
        customerPhoneNumber: _customerPhoneController.text.trim(),
        customerName2: _hasSecondCustomer
            ? _customer2NameController.text.trim()
            : null,
        customerGender2: _hasSecondCustomer ? _customer2Gender : null,
        customerPhoneNumber2: _hasSecondCustomer
            ? _customer2PhoneController.text.trim()
            : null,
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
      Get.snackbar(
        l10n.depositRequested,
        l10n.depositRequestedMessage(widget.unit.name),
      );
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  @override
  void dispose() {
    _depositAmountController.dispose();
    _otherDiscountController.dispose();
    _customerNameController.dispose();
    _customerPhoneController.dispose();
    _customer2NameController.dispose();
    _customer2PhoneController.dispose();
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
      appBar: AppBar(title: Text(l10n.requestDeposit)),
      body: _loadingOptions
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.fromLTRB(20, 12, 20, 32),
              children: [
                AppPanel(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      AppSectionTitle(l10n.unit),
                      Text(
                        widget.unit.name,
                        style: Theme.of(context).textTheme.titleLarge,
                      ),
                      if (widget.unit.price != null)
                        Text(l10n.listedPrice(formatUsd(widget.unit.price!))),
                    ],
                  ),
                ),
                const SizedBox(height: 20),
                AppPanel(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      AppSectionTitle(l10n.customer),
                      TextField(
                        controller: _customerNameController,
                        decoration: InputDecoration(
                          labelText: l10n.customerName,
                        ),
                      ),
                      const SizedBox(height: 12),
                      _GenderDropdown(
                        value: _customerGender,
                        onChanged: (v) => setState(() => _customerGender = v!),
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: _customerPhoneController,
                        keyboardType: TextInputType.phone,
                        decoration: InputDecoration(
                          labelText: l10n.customerPhone,
                        ),
                      ),
                      const SizedBox(height: 8),
                      CheckboxListTile(
                        contentPadding: EdgeInsets.zero,
                        title: Text(l10n.addSecondCustomer),
                        value: _hasSecondCustomer,
                        onChanged: (v) =>
                            setState(() => _hasSecondCustomer = v ?? false),
                      ),
                      if (_hasSecondCustomer) ...[
                        TextField(
                          controller: _customer2NameController,
                          decoration: InputDecoration(
                            labelText: l10n.secondCustomerName,
                          ),
                        ),
                        const SizedBox(height: 12),
                        _GenderDropdown(
                          value: _customer2Gender,
                          onChanged: (v) =>
                              setState(() => _customer2Gender = v),
                        ),
                        const SizedBox(height: 12),
                        TextField(
                          controller: _customer2PhoneController,
                          keyboardType: TextInputType.phone,
                          decoration: InputDecoration(
                            labelText: l10n.secondCustomerPhone,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
                const SizedBox(height: 20),
                AppPanel(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      AppSectionTitle(l10n.depositAndDiscount),
                      TextField(
                        controller: _depositAmountController,
                        keyboardType: const TextInputType.numberWithOptions(
                          decimal: true,
                        ),
                        decoration: InputDecoration(
                          labelText: l10n.depositAmount,
                        ),
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: _otherDiscountController,
                        keyboardType: const TextInputType.numberWithOptions(
                          decimal: true,
                        ),
                        decoration: InputDecoration(
                          labelText: l10n.otherDiscountAllowance,
                        ),
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: _remarkController,
                        decoration: InputDecoration(labelText: l10n.remark),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 20),
                AppPanel(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      AppSectionTitle(l10n.paymentOption),
                      if (_paymentOptions.isNotEmpty)
                        SwitchListTile(
                          contentPadding: EdgeInsets.zero,
                          title: Text(l10n.useCustomLoanTerms),
                          value: _useCustomLoanTerms,
                          onChanged: (v) =>
                              setState(() => _useCustomLoanTerms = v),
                        ),
                      if (!_useCustomLoanTerms)
                        DropdownButtonFormField<PaymentOption>(
                          isExpanded: true,
                          initialValue: _selectedPaymentOption,
                          decoration: InputDecoration(
                            labelText: l10n.paymentOption,
                          ),
                          items: _paymentOptions
                              .map(
                                (o) => DropdownMenuItem(
                                  value: o,
                                  child: Text(o.name),
                                ),
                              )
                              .toList(),
                          onChanged: (v) =>
                              setState(() => _selectedPaymentOption = v),
                        )
                      else ...[
                        TextField(
                          controller: _loanDurationController,
                          keyboardType: TextInputType.number,
                          decoration: InputDecoration(
                            labelText: l10n.loanDurationYears,
                          ),
                        ),
                        const SizedBox(height: 12),
                        TextField(
                          controller: _interestController,
                          keyboardType: const TextInputType.numberWithOptions(
                            decimal: true,
                          ),
                          decoration: InputDecoration(
                            labelText: l10n.annualInterestRate,
                          ),
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
                    ],
                  ),
                ),
                const SizedBox(height: 20),
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
                      : Text(l10n.submitDepositRequest),
                ),
              ],
            ),
    );
  }
}

class _GenderDropdown extends StatelessWidget {
  const _GenderDropdown({required this.value, required this.onChanged});

  final int? value;
  final ValueChanged<int?> onChanged;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    return DropdownButtonFormField<int>(
      isExpanded: true,
      initialValue: value,
      decoration: InputDecoration(labelText: l10n.gender),
      items: [
        DropdownMenuItem(value: 1, child: Text(l10n.male)),
        DropdownMenuItem(value: 2, child: Text(l10n.female)),
      ],
      onChanged: onChanged,
    );
  }
}

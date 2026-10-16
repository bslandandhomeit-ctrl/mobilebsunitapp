import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/widgets/app_ui.dart';
import '../../../l10n/app_localizations.dart';
import '../../projects/data/payment_option_repository.dart';
import '../../projects/models/payment_option.dart';
import '../../projects/models/unit.dart';
import '../../projects/views/unit_information_card.dart';
import '../data/deposit_repository.dart';

class DepositRequestFormView extends StatefulWidget {
  const DepositRequestFormView({super.key, required this.unit});
  final Unit unit;
  @override
  State<DepositRequestFormView> createState() => _DepositRequestFormViewState();
}

class _DepositRequestFormViewState extends State<DepositRequestFormView> {
  final _repository = DepositRepository();
  final _form = GlobalKey<FormState>();
  final _scroll = ScrollController();
  final _name = TextEditingController(), _name2 = TextEditingController();
  final _phone = TextEditingController(), _phone2 = TextEditingController();
  final _deposit = TextEditingController(),
      _discount = TextEditingController(text: '0');
  final _remark = TextEditingController(),
      _special = TextEditingController(text: '0');
  final _downAmount = TextEditingController(text: '0'),
      _downDuration = TextEditingController(text: '0');
  final _interest = TextEditingController(text: '0'),
      _duration = TextEditingController(text: '0');
  int _step = 0, _paymentChoice = 0;
  int? _gender, _gender2;
  bool _downPayment = false,
      _specialOther = false,
      _saving = false,
      _loadingOptions = true;
  String? _error, _optionsError;
  List<PaymentOption> _options = [];
  @override
  void initState() {
    super.initState();
    _loadOptions();
  }

  Future<void> _loadOptions() async {
    setState(() {
      _loadingOptions = true;
      _optionsError = null;
    });
    try {
      if (widget.unit.unitTypeId != null) {
        _options = (await PaymentOptionRepository().getByUnitTypeId(
          widget.unit.unitTypeId!,
        )).items;
      }
    } catch (_) {
      if (mounted) {
        _optionsError = AppLocalizations.of(context)!.paymentOptionsLoadError;
      }
    } finally {
      if (mounted) setState(() => _loadingOptions = false);
    }
  }

  @override
  void dispose() {
    for (final c in [
      _name,
      _name2,
      _phone,
      _phone2,
      _deposit,
      _discount,
      _remark,
      _special,
      _downAmount,
      _downDuration,
      _interest,
      _duration,
    ]) {
      c.dispose();
    }
    _scroll.dispose();
    super.dispose();
  }

  void _go(int step) {
    FocusManager.instance.primaryFocus?.unfocus();
    setState(() {
      _step = step;
      _error = null;
    });
    if (_scroll.hasClients) _scroll.jumpTo(0);
  }

  void _next() {
    if (_form.currentState!.validate()) _go(_step + 1);
  }

  Future<void> _save() async {
    if (!_form.currentState!.validate()) return;
    FocusManager.instance.primaryFocus?.unfocus();
    setState(() {
      _saving = true;
      _error = null;
    });
    final custom = _paymentChoice == -1;
    try {
      await _repository.create(
        unitId: widget.unit.id,
        depositAmount: double.parse(_deposit.text.trim()),
        otherDiscountAllowance: double.parse(_discount.text.trim()),
        customerName: _name.text.trim(),
        customerGender: _gender!,
        customerPhoneNumber: _phone.text.trim(),
        customerName2: _name2.text.trim().isEmpty ? null : _name2.text.trim(),
        customerGender2: _name2.text.trim().isEmpty ? null : _gender2,
        customerPhoneNumber2: _phone2.text.trim().isEmpty
            ? null
            : _phone2.text.trim(),
        remark: _remark.text.trim().isEmpty ? null : _remark.text.trim(),
        paymentOptionId: _paymentChoice > 0 ? _paymentChoice : null,
        loanDuration: custom ? int.parse(_duration.text.trim()) : null,
        interest: custom ? double.parse(_interest.text.trim()) : null,
        specialDiscount: custom
            ? (_specialOther ? double.parse(_special.text.trim()) : 0)
            : null,
        isFirstPayment: custom ? _downPayment : null,
        firstPaymentDuration: custom
            ? (_downPayment ? int.parse(_downDuration.text.trim()) : 0)
            : null,
        firstPaymentAmount: custom
            ? (_downPayment ? double.parse(_downAmount.text.trim()) : 0)
            : null,
      );
      if (!mounted) return;
      final l = AppLocalizations.of(context)!;
      Get.back(result: true);
      Get.snackbar(
        l.depositRequested,
        l.depositRequestedMessage(widget.unit.name),
      );
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  String? _required(String? value) => value == null || value.trim().isEmpty
      ? AppLocalizations.of(context)!.requiredFieldLabel
      : null;
  String? _phoneValid(String? value, {bool optional = false}) {
    if (optional && (value ?? '').trim().isEmpty) return null;
    return RegExp(r'^0\d{8,9}$').hasMatch((value ?? '').trim())
        ? null
        : AppLocalizations.of(context)!.invalidPhoneLabel;
  }

  Widget _money(
    TextEditingController controller,
    String label, {
    String? note,
    double max = 999999999.99,
    bool integer = false,
    bool positive = false,
    bool enabled = true,
  }) {
    final l = AppLocalizations.of(context)!;
    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: TextFormField(
        controller: controller,
        enabled: enabled,
        keyboardType: TextInputType.numberWithOptions(decimal: !integer),
        decoration: InputDecoration(
          labelText: label,
          helperText: note,
          helperMaxLines: 6,
        ),
        validator: (v) {
          if (!enabled) return null;
          final value = double.tryParse((v ?? '').trim());
          if (value == null ||
              !value.isFinite ||
              value < (positive ? .01 : 0) ||
              value > max ||
              (integer && value != value.roundToDouble())) {
            return l.invalidNumberLabel;
          }
          return null;
        },
      ),
    );
  }

  Widget _customerStep(AppLocalizations l) => AppPanel(
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        AppSectionTitle(l.customerInformationTitle),
        TextFormField(
          controller: _name,
          validator: _required,
          maxLength: 255,
          decoration: InputDecoration(
            labelText: l.customerOneNameLabel,
            counterText: '',
          ),
        ),
        const SizedBox(height: 16),
        _genderField(l, false),
        const SizedBox(height: 24),
        TextFormField(
          controller: _name2,
          maxLength: 255,
          decoration: InputDecoration(
            labelText: l.customerTwoNameLabel,
            counterText: '',
          ),
        ),
        const SizedBox(height: 16),
        _genderField(l, true),
        const SizedBox(height: 24),
        Text(
          l.customerPhonesTitle,
          style: Theme.of(context).textTheme.titleSmall,
        ),
        const SizedBox(height: 12),
        TextFormField(
          controller: _phone,
          validator: _phoneValid,
          keyboardType: TextInputType.phone,
          decoration: InputDecoration(
            labelText: l.phoneLineOneLabel,
            hintText: '012345678',
          ),
        ),
        const SizedBox(height: 16),
        TextFormField(
          controller: _phone2,
          validator: (v) => _phoneValid(v, optional: true),
          keyboardType: TextInputType.phone,
          decoration: InputDecoration(
            labelText: l.phoneLineTwoLabel,
            hintText: '012345678',
          ),
        ),
      ],
    ),
  );
  Widget _genderField(AppLocalizations l, bool second) =>
      DropdownButtonFormField<int>(
        key: ValueKey('gender-$second'),
        initialValue: second ? _gender2 : _gender,
        isExpanded: true,
        decoration: InputDecoration(
          labelText: second
              ? l.customerTwoGenderLabel
              : l.customerOneGenderLabel,
        ),
        items: [
          DropdownMenuItem(value: 1, child: Text(l.male)),
          DropdownMenuItem(value: 2, child: Text(l.female)),
        ],
        validator: (v) =>
            v == null && (!second || _name2.text.trim().isNotEmpty)
            ? l.requiredFieldLabel
            : null,
        onChanged: (v) => setState(() {
          if (second) {
            _gender2 = v;
          } else {
            _gender = v;
          }
        }),
      );
  Widget _discountStep(AppLocalizations l) => AppPanel(
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        AppSectionTitle(l.otherDiscountDepositTitle),
        _money(
          _discount,
          '${l.otherDiscountAllowance} (\$)',
          note: l.otherDiscountNote,
        ),
        _money(_deposit, '${l.depositAmount} (\$) *'),
        TextFormField(
          readOnly: true,
          initialValue: formatUsd(widget.unit.promotionAmount ?? 0),
          decoration: InputDecoration(
            labelText: l.promotionalDiscountLabel,
            helperText: l.promotionalDiscountNote,
            helperMaxLines: 3,
          ),
        ),
      ],
    ),
  );
  Widget _paymentStep(AppLocalizations l) {
    final selected = _options.where((o) => o.id == _paymentChoice).firstOrNull;
    return AppPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          AppSectionTitle(l.paymentOption),
          TextFormField(
            controller: _remark,
            minLines: 3,
            maxLines: 5,
            maxLength: 500,
            decoration: InputDecoration(
              labelText: l.remark,
              hintText: l.remarkPlaceholder,
            ),
          ),
          const SizedBox(height: 16),
          if (_loadingOptions) const LinearProgressIndicator(),
          if (_optionsError != null) ...[
            Text(_optionsError!),
            TextButton(onPressed: _loadOptions, child: Text(l.retry)),
          ],
          DropdownButtonFormField<int>(
            key: ValueKey('payment-$_paymentChoice'),
            initialValue: _paymentChoice,
            isExpanded: true,
            decoration: InputDecoration(labelText: l.paymentOption),
            items: [
              DropdownMenuItem(value: 0, child: Text(l.noneLabel)),
              ..._options.map(
                (o) => DropdownMenuItem(
                  value: o.id,
                  child: Text(
                    o.name,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              ),
              DropdownMenuItem(value: -1, child: Text(l.otherLabel)),
            ],
            onChanged: _loadingOptions
                ? null
                : (v) => setState(() => _paymentChoice = v ?? 0),
          ),
          const SizedBox(height: 12),
          Text(
            l.otherPaymentOptionNote,
            style: Theme.of(context).textTheme.bodySmall,
          ),
          const SizedBox(height: 20),
          if (selected != null) ...[
            _summary(
              l.specialDiscountPercent,
              '${selected.specialDiscount ?? 0}%',
            ),
            _summary(l.annualInterestRate, '${selected.interest ?? 0}%'),
            _summary(l.installmentMonthsLabel, '${selected.loanDuration ?? 0}'),
            _summary(
              l.downPaymentLabel,
              selected.isFirstPayment ? l.yes : l.no,
            ),
            if (selected.isFirstPayment) ...[
              _summary(
                l.firstPaymentPercentage,
                '${selected.firstPaymentPercentage ?? 0}%',
              ),
              _summary(
                l.firstPaymentDurationMonths,
                '${selected.firstPaymentDuration ?? 0}',
              ),
            ],
          ],
          if (_paymentChoice <= 0) ...[
            if (_paymentChoice == 0)
              Padding(
                padding: const EdgeInsets.only(bottom: 16),
                child: Text(
                  l.customPaymentHint,
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ),
            Text(
              l.specialDiscountPercent,
              style: Theme.of(context).textTheme.titleSmall,
            ),
            const SizedBox(height: 8),
            SegmentedButton<bool>(
              segments: [
                ButtonSegment(value: false, label: Text(l.standardLabel)),
                ButtonSegment(value: true, label: Text(l.otherLabel)),
              ],
              selected: {_specialOther},
              onSelectionChanged: _paymentChoice == 0
                  ? null
                  : (v) => setState(() => _specialOther = v.first),
            ),
            const SizedBox(height: 16),
            if (_specialOther)
              _money(
                _special,
                l.specialDiscountPercent,
                max: 100,
                enabled: _paymentChoice == -1,
              ),
            DropdownButtonFormField<bool>(
              initialValue: _downPayment,
              isExpanded: true,
              decoration: InputDecoration(labelText: l.downPaymentLabel),
              items: [
                DropdownMenuItem(value: false, child: Text(l.no)),
                DropdownMenuItem(value: true, child: Text(l.yes)),
              ],
              onChanged: _paymentChoice == 0
                  ? null
                  : (v) => setState(() => _downPayment = v ?? false),
            ),
            const SizedBox(height: 16),
            ...[
              _money(
                _downAmount,
                l.downPaymentAmountLabel,
                positive: true,
                enabled: _paymentChoice == -1 && _downPayment,
              ),
              _money(
                _downDuration,
                l.firstPaymentDurationMonths,
                integer: true,
                positive: true,
                enabled: _paymentChoice == -1 && _downPayment,
              ),
            ],
            _money(
              _interest,
              l.annualInterestRate,
              enabled: _paymentChoice == -1,
            ),
            _money(
              _duration,
              l.installmentMonthsLabel,
              integer: true,
              enabled: _paymentChoice == -1,
            ),
          ],
        ],
      ),
    );
  }

  Widget _summary(String label, String value) => Padding(
    padding: const EdgeInsets.only(bottom: 12),
    child: Wrap(
      spacing: 12,
      runSpacing: 4,
      children: [
        Text(label),
        Text(value, style: const TextStyle(fontWeight: FontWeight.w700)),
      ],
    ),
  );
  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context)!;
    final titles = [
      l.customerInformationTitle,
      l.unitInformationTitle,
      l.otherDiscountDepositTitle,
      l.paymentOption,
    ];
    return PopScope(
      canPop: _step == 0 && !_saving,
      onPopInvokedWithResult: (didPop, result) {
        if (!didPop && !_saving && _step > 0) _go(_step - 1);
      },
      child: AppPage(
        appBar: AppBar(title: Text(l.requestDeposit)),
        body: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 8, 20, 16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    children: [
                      Text(
                        '${_step + 1} / 4',
                        style: Theme.of(context).textTheme.labelLarge?.copyWith(
                          color: Theme.of(context).colorScheme.primary,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Text(
                          titles[_step],
                          style: Theme.of(context).textTheme.titleSmall,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      for (var i = 0; i < 4; i++)
                        Expanded(
                          child: Container(
                            height: 4,
                            margin: EdgeInsets.only(right: i == 3 ? 0 : 6),
                            decoration: BoxDecoration(
                              color: i <= _step
                                  ? Theme.of(context).colorScheme.primary
                                  : Theme.of(context)
                                        .colorScheme
                                        .outlineVariant,
                              borderRadius: BorderRadius.circular(4),
                            ),
                          ),
                        ),
                    ],
                  ),
                ],
              ),
            ),
            Expanded(
              child: Form(
                key: _form,
                child: ListView(
                  controller: _scroll,
                  padding: const EdgeInsets.fromLTRB(20, 0, 20, 16),
                  children: [
                    KeyedSubtree(
                      key: ValueKey(_step),
                      child: switch (_step) {
                        0 => _customerStep(l),
                        1 => UnitInformationCard(unit: widget.unit),
                        2 => _discountStep(l),
                        _ => _paymentStep(l),
                      },
                    ),
                    if (_error != null)
                      Padding(
                        padding: const EdgeInsets.only(top: 16),
                        child: Text(
                          _error!,
                          style: TextStyle(
                            color: Theme.of(context).colorScheme.error,
                          ),
                        ),
                      ),
                  ],
                ),
              ),
            ),
            SafeArea(
              top: false,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(20, 8, 20, 12),
                child: Row(
                  children: [
                    if (_step > 0) ...[
                      Expanded(
                        child: OutlinedButton(
                          onPressed: _saving ? null : () => _go(_step - 1),
                          child: Text(l.back),
                        ),
                      ),
                      const SizedBox(width: 12),
                    ],
                    Expanded(
                      child: FilledButton(
                        onPressed: _saving
                            ? null
                            : (_step == 3 ? _save : _next),
                        child: _saving
                            ? const SizedBox(
                                width: 20,
                                height: 20,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                ),
                              )
                            : Text(_step == 3 ? l.saveLabel : l.nextLabel),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

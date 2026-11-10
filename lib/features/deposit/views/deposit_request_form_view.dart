import 'dart:io';

import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:image_picker/image_picker.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/widgets/app_ui.dart';
import '../../../l10n/app_localizations.dart';
import '../../projects/data/payment_option_repository.dart';
import '../../projects/models/payment_option.dart';
import '../../projects/models/unit.dart';
import '../../projects/views/unit_information_card.dart';
import '../data/deposit_repository.dart';
import '../data/payment_qr_repository.dart';
import 'customer_picker_sheet.dart';
import 'deposit_pre_receipt_view.dart';

class DepositRequestFormView extends StatefulWidget {
  const DepositRequestFormView({super.key, required this.unit});
  final Unit unit;
  @override
  State<DepositRequestFormView> createState() => _DepositRequestFormViewState();
}

class _AdditionalCustomer {
  final name = TextEditingController();
  final phone = TextEditingController();
  int? gender;

  void dispose() {
    name.dispose();
    phone.dispose();
  }
}

class _DepositRequestFormViewState extends State<DepositRequestFormView> {
  final _repository = DepositRepository();
  final _additionalCustomers = <_AdditionalCustomer>[];
  final _form = GlobalKey<FormState>();
  final _scroll = ScrollController();
  // Customer 1 (required) and optional Customer 2; "Add customer" adds more
  // buyers into _additionalCustomers (customers 3 and up).
  final _name = TextEditingController(), _phone = TextEditingController();
  final _name2 = TextEditingController(), _phone2 = TextEditingController();
  // Number fields start empty (not pre-filled with "0") so the user doesn't
  // have to clear a 0 first. A blank optional field counts as 0 on submit.
  final _deposit = TextEditingController(), _discount = TextEditingController();
  final _remark = TextEditingController(), _special = TextEditingController();
  final _downAmount = TextEditingController(),
      _downDuration = TextEditingController();
  final _interest = TextEditingController(),
      _duration = TextEditingController();
  int _step = 0, _paymentChoice = 0;
  final _picker = ImagePicker();
  XFile? _idFront, _idBack, _transferProof;
  PaymentQr? _qr;
  String? _qrError;
  bool _qrLoading = true, _useOption = false;
  int? _gender, _gender2;
  bool _secondCustomer = false,
      _downPayment = false,
      _specialOther = false,
      _saving = false,
      _loadingOptions = true;
  String? _error, _optionsError;
  List<PaymentOption> _options = [];

  @override
  void initState() {
    super.initState();
    _loadOptions();
    _loadQr();
  }

  Future<void> _loadQr() async {
    setState(() {
      _qrLoading = true;
      _qrError = null;
    });
    try {
      _qr = await PaymentQrRepository().forUnit(widget.unit.id);
    } catch (e) {
      _qrError = e.toString();
    } finally {
      if (mounted) setState(() => _qrLoading = false);
    }
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
      _phone,
      _name2,
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
    for (final customer in _additionalCustomers) {
      customer.dispose();
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
    final custom = _useOption && _paymentChoice == -1;
    try {
      final created = await _repository.create(
        unitId: widget.unit.id,
        depositAmount: double.parse(_deposit.text.trim()),
        otherDiscountAllowance:
            0, // the other-discount field was removed from the form
        customerName: _name.text.trim(),
        customerGender: _gender!,
        customerPhoneNumber: _phone.text.trim(),
        customerName2: _secondCustomer ? _name2.text.trim() : null,
        customerGender2: _secondCustomer ? _gender2 : null,
        customerPhoneNumber2: _phone2.text.trim().isEmpty
            ? null
            : _phone2.text.trim(),
        additionalCustomers: [
          for (final customer in _additionalCustomers)
            {
              'name': customer.name.text.trim(),
              'gender': customer.gender!,
              'phone_number': customer.phone.text.trim(),
            },
        ],
        remark: _remark.text.trim().isEmpty ? null : _remark.text.trim(),
        paymentOptionId: _useOption && _paymentChoice > 0
            ? _paymentChoice
            : null,
        loanDuration: custom
            ? (int.tryParse(_duration.text.trim()) ?? 0)
            : null,
        interest: custom ? (double.tryParse(_interest.text.trim()) ?? 0) : null,
        specialDiscount: custom
            ? (_specialOther ? (double.tryParse(_special.text.trim()) ?? 0) : 0)
            : null,
        isFirstPayment: custom ? _downPayment : null,
        firstPaymentDuration: custom
            ? (_downPayment
                  ? (int.tryParse(_downDuration.text.trim()) ?? 0)
                  : 0)
            : null,
        firstPaymentAmount: custom
            ? (_downPayment
                  ? (double.tryParse(_downAmount.text.trim()) ?? 0)
                  : 0)
            : null,
      );
      if (!mounted) return;
      // Photos (ID card, transfer proof) go to the customer record; a failure does not undo the saved deposit.
      if (_idFront != null || _idBack != null || _transferProof != null) {
        try {
          await _repository.uploadAttachments(
            created.id,
            idFrontPath: _idFront?.path,
            idBackPath: _idBack?.path,
            transferProofPath: _transferProof?.path,
          );
        } catch (e) {
          if (mounted) {
            Get.snackbar(
              '',
              AppLocalizations.of(context)!
                  .depAttachmentsFailed(e is ApiException ? e.message : '$e'),
            );
          }
        }
      }
      // The letterhead (company name, logo) comes with the QR details; ask again if that first call had failed.
      var qr = _qr;
      if (qr == null) {
        try {
          qr = await PaymentQrRepository().forUnit(widget.unit.id);
        } catch (_) {}
      }
      // Show the pre-receipt, then go back to the unit list.
      await Get.to(
        () => DepositPreReceiptView(
          data: PreReceiptData(
            customer: _name.text.trim(),
            phone: _phone.text.trim(),
            unit: widget.unit.code,
            project: widget.unit.projectName,
            depositAmount: double.parse(_deposit.text.trim()),
            promotionDiscount: widget.unit.promotionAmount ?? 0,
            date: DateTime.now(),
            number: created.details['pre_receipt_no']?.toString(),
            seller: currentSellerName(),
            letterhead: qr,
          ),
        ),
      );
      Get.back(result: true);
    } on ApiException catch (e) {
      if (mounted) {
        setState(() => _error = e.message);
        if (_scroll.hasClients) _scroll.jumpTo(0);
      }
    } catch (e) {
      // Anything unexpected (bad number, network failure) is shown instead of failing silently.
      if (mounted) {
        setState(
          () => _error = e is FormatException
              ? 'Please check the amounts you entered.'
              : e.toString(),
        );
        if (_scroll.hasClients) _scroll.jumpTo(0);
      }
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
          final text = (v ?? '').trim();
          // Blank is fine for an optional field and means 0; a "positive"
          // field (e.g. the down-payment amount) still needs a real value.
          if (text.isEmpty) return positive ? l.invalidNumberLabel : null;
          final value = double.tryParse(text);
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

  // Pick a customer created in Be Loan and fill the fields from it. Optional: every field can still be typed.
  Future<void> _fillCustomer({
    required TextEditingController name,
    TextEditingController? phone,
    TextEditingController? phone2,
    required void Function(int gender) setGender,
  }) async {
    final c = await pickCustomer(context);
    if (c == null || !mounted) return;
    setState(() {
      name.text = c.name;
      if (c.gender != null) setGender(c.gender!);
      if (phone != null && (c.phone ?? '').isNotEmpty) phone.text = c.phone!;
      if (phone2 != null &&
          (c.phone2 ?? '').isNotEmpty &&
          phone2.text.trim().isEmpty) {
        phone2.text = c.phone2!;
      }
    });
  }

  Widget _findCustomerButton(AppLocalizations l, VoidCallback onPressed) =>
      Align(
        alignment: Alignment.centerLeft,
        child: TextButton.icon(
          onPressed: onPressed,
          icon: const Icon(Icons.person_search_outlined),
          label: Text(l.findCustomer),
        ),
      );

  Widget _qrStep(AppLocalizations l) => AppPanel(
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        AppSectionTitle(l.scanToPayTitle),
        Text(l.scanToPayNote, style: Theme.of(context).textTheme.bodySmall),
        const SizedBox(height: 16),
        if (_qrLoading) const Center(child: CircularProgressIndicator()),
        if (_qrError != null) ...[
          Text(_qrError!),
          TextButton(onPressed: _loadQr, child: Text(l.retry)),
        ],
        if (!_qrLoading && _qr != null) ...[
          if ((_qr!.qrUrl ?? '').isNotEmpty)
            Container(
              color: Colors.white,
              padding: const EdgeInsets.all(8),
              child: Image.network(
                _qr!.qrUrl!,
                height: 320,
                fit: BoxFit.contain,
                errorBuilder: (_, _, _) => Text(l.scanToPayNoQr),
              ),
            )
          else
            Text(l.scanToPayNoQr),
          const SizedBox(height: 16),
          if ((_qr!.bankName ?? '').isNotEmpty) Text(_qr!.bankName!),
          if ((_qr!.accountName ?? '').isNotEmpty)
            _summary(l.bankAccountNameLabel, _qr!.accountName!),
          if ((_qr!.accountNumber ?? '').isNotEmpty)
            _summary(l.bankAccountNumberLabel, _qr!.accountNumber!),
        ],
      ],
    ),
  );
  Widget _customerStep(AppLocalizations l) => AppPanel(
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        AppSectionTitle(l.customerInformationTitle),
        _findCustomerButton(
          l,
          () => _fillCustomer(
            name: _name,
            phone: _phone,
            phone2: _phone2,
            setGender: (g) => _gender = g,
          ),
        ),
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
        const SizedBox(height: 16),
        TextFormField(
          controller: _phone,
          validator: _phoneValid,
          keyboardType: TextInputType.phone,
          decoration: InputDecoration(
            labelText: l.phoneLineOneLabel,
            hintText: '012345678',
          ),
        ),
        const SizedBox(height: 24),
        SwitchListTile.adaptive(
          contentPadding: EdgeInsets.zero,
          title: Text(l.addSecondCustomer),
          value: _secondCustomer,
          onChanged: _additionalCustomers.isNotEmpty
              ? null
              : (value) => setState(() => _secondCustomer = value),
        ),
        if (_secondCustomer) ...[
          const SizedBox(height: 8),
          _findCustomerButton(
            l,
            () => _fillCustomer(name: _name2, setGender: (g) => _gender2 = g),
          ),
          TextFormField(
            controller: _name2,
            validator: _required,
            maxLength: 255,
            decoration: InputDecoration(
              labelText: l.customerTwoNameLabel,
              counterText: '',
            ),
          ),
          const SizedBox(height: 16),
          _genderField(l, true),
        ],
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
        for (final (index, customer) in _additionalCustomers.indexed)
          Padding(
            key: ObjectKey(customer),
            padding: const EdgeInsets.only(top: 24),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  children: [
                    Expanded(child: Text(l.depositCustomerNumber(index + 3))),
                    IconButton(
                      tooltip: l.removeDepositCustomer,
                      icon: const Icon(Icons.person_remove_outlined),
                      onPressed: () {
                        setState(() => _additionalCustomers.remove(customer));
                        // Wait until the text fields have unmounted.
                        WidgetsBinding.instance.addPostFrameCallback((_) {
                          customer.dispose();
                        });
                      },
                    ),
                  ],
                ),
                _findCustomerButton(
                  l,
                  () => _fillCustomer(
                    name: customer.name,
                    phone: customer.phone,
                    setGender: (g) => customer.gender = g,
                  ),
                ),
                TextFormField(
                  controller: customer.name,
                  validator: _required,
                  maxLength: 255,
                  decoration: InputDecoration(labelText: l.customerName),
                ),
                const SizedBox(height: 16),
                DropdownButtonFormField<int>(
                  key: ValueKey('gender-extra-${customer.gender}'),
                  initialValue: customer.gender,
                  isExpanded: true,
                  decoration: InputDecoration(
                    labelText: l.depositCustomerGender,
                  ),
                  items: [
                    DropdownMenuItem(value: 1, child: Text(l.male)),
                    DropdownMenuItem(value: 2, child: Text(l.female)),
                  ],
                  validator: (v) => v == null ? l.requiredFieldLabel : null,
                  onChanged: (v) => setState(() => customer.gender = v),
                ),
                const SizedBox(height: 16),
                TextFormField(
                  controller: customer.phone,
                  validator: (v) => _phoneValid(v, optional: true),
                  keyboardType: TextInputType.phone,
                  decoration: InputDecoration(
                    labelText: l.customerPhone,
                    hintText: '012345678',
                  ),
                ),
              ],
            ),
          ),
        const SizedBox(height: 16),
        OutlinedButton.icon(
          icon: const Icon(Icons.person_add_alt_1),
          label: Text(l.addDepositCustomer),
          onPressed: () => setState(() {
            if (!_secondCustomer) {
              _secondCustomer = true;
            } else {
              _additionalCustomers.add(_AdditionalCustomer());
            }
          }),
        ),
      ],
    ),
  );
  Widget _genderField(
    AppLocalizations l,
    bool second,
  ) => DropdownButtonFormField<int>(
    key: ValueKey('gender-$second-${second ? _gender2 : _gender}'),
    initialValue: second ? _gender2 : _gender,
    isExpanded: true,
    decoration: InputDecoration(
      labelText: second ? l.customerTwoGenderLabel : l.customerOneGenderLabel,
    ),
    items: [
      DropdownMenuItem(value: 1, child: Text(l.male)),
      DropdownMenuItem(value: 2, child: Text(l.female)),
    ],
    validator: (v) =>
        v == null && (!second || _secondCustomer) ? l.requiredFieldLabel : null,
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
  Future<void> _pickPhoto(void Function(XFile?) assign) async {
    final l = AppLocalizations.of(context)!;
    final source = await showModalBottomSheet<ImageSource>(
      context: context,
      builder: (_) => SafeArea(
        child: Wrap(
          children: [
            ListTile(
              leading: const Icon(Icons.photo_camera),
              title: Text(l.depTakePhoto),
              onTap: () => Navigator.pop(context, ImageSource.camera),
            ),
            ListTile(
              leading: const Icon(Icons.photo_library),
              title: Text(l.depFromGallery),
              onTap: () => Navigator.pop(context, ImageSource.gallery),
            ),
          ],
        ),
      ),
    );
    if (source == null) return;
    final picked = await _picker.pickImage(source: source, imageQuality: 85);
    if (picked != null && mounted) setState(() => assign(picked));
  }

  Widget _photoTile(String label, XFile? file, void Function(XFile?) assign) {
    final l = AppLocalizations.of(context)!;
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: InkWell(
        onTap: () => _pickPhoto(assign),
        child: Container(
          padding: const EdgeInsets.all(10),
          decoration: BoxDecoration(
            border: Border.all(
              color: Theme.of(context).colorScheme.outlineVariant,
            ),
            borderRadius: BorderRadius.circular(8),
          ),
          child: Row(
            children: [
              SizedBox(
                width: 72,
                height: 56,
                child: file == null
                    ? const Icon(Icons.add_a_photo_outlined, size: 32)
                    : Image.file(File(file.path), fit: BoxFit.cover),
              ),
              const SizedBox(width: 12),
              Expanded(child: Text(label)),
              if (file != null)
                IconButton(
                  tooltip: l.depRemovePhoto,
                  icon: const Icon(Icons.close),
                  onPressed: () => setState(() => assign(null)),
                ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _attachmentsSection(AppLocalizations l) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      const SizedBox(height: 8),
      AppSectionTitle(l.depAttachmentsTitle),
      _photoTile(l.depIdFront, _idFront, (f) => _idFront = f),
      _photoTile(l.depIdBack, _idBack, (f) => _idBack = f),
      _photoTile(l.depTransferProof, _transferProof, (f) => _transferProof = f),
    ],
  );

  Widget _paymentStep(AppLocalizations l) {
    final selected = _options.where((o) => o.id == _paymentChoice).firstOrNull;
    return AppPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          AppSectionTitle(l.paymentOption),
          SegmentedButton<bool>(
            segments: [
              ButtonSegment(value: false, label: Text(l.paymentOptionSkip)),
              ButtonSegment(value: true, label: Text(l.paymentOptionAdd)),
            ],
            selected: {_useOption},
            onSelectionChanged: (v) => setState(() {
              _useOption = v.first;
              if (!_useOption) _paymentChoice = 0;
            }),
          ),
          const SizedBox(height: 12),
          if (!_useOption)
            Padding(
              padding: const EdgeInsets.only(bottom: 16),
              child: Text(
                l.paymentOptionSkipNote,
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ),
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
          if (_useOption) ...[
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
              _summary(
                l.installmentMonthsLabel,
                '${selected.loanDuration ?? 0}',
              ),
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
          _attachmentsSection(l),
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
      l.scanToPayTitle,
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
                        '${_step + 1} / 5',
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
                      for (var i = 0; i < 5; i++)
                        Expanded(
                          child: Container(
                            height: 4,
                            margin: EdgeInsets.only(right: i == 4 ? 0 : 6),
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
                    KeyedSubtree(
                      key: ValueKey(_step),
                      child: switch (_step) {
                        0 => _qrStep(l),
                        1 => _customerStep(l),
                        2 => UnitInformationCard(unit: widget.unit),
                        3 => _discountStep(l),
                        _ => _paymentStep(l),
                      },
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
                            : (_step == 4 ? _save : _next),
                        child: _saving
                            ? const SizedBox(
                                width: 20,
                                height: 20,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                ),
                              )
                            : Text(_step == 4 ? l.saveLabel : l.nextLabel),
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

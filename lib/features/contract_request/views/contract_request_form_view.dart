import 'dart:io';

import 'package:flutter/material.dart';

import 'package:get/get.dart';
import 'package:image_picker/image_picker.dart';
import 'package:intl/intl.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/widgets/app_ui.dart';
import '../../../l10n/app_localizations.dart';
import '../../deposit/models/unit_deposit_request.dart';
import '../data/contract_request_repository.dart';

class ContractRequestFormView extends StatefulWidget {
  const ContractRequestFormView({super.key, required this.depositRequest});

  final UnitDepositRequest depositRequest;

  @override
  State<ContractRequestFormView> createState() =>
      _ContractRequestFormViewState();
}

class _ContractRequestFormViewState extends State<ContractRequestFormView> {
  final _repository = ContractRequestRepository();
  final _picker = ImagePicker();
  final _attachments = <XFile>[];
  final _dateFormat = DateFormat('dd-MM-yyyy');
  DateTime _signedAt = DateTime.now();
  DateTime _startPaymentDate = DateTime.now();
  bool _isSubmitting = false;
  String? _error;

  Future<void> _addFromGallery() async {
    final picked = await _picker.pickMultiImage(imageQuality: 85);
    if (picked.isNotEmpty) setState(() => _attachments.addAll(picked));
  }

  Future<void> _addFromCamera() async {
    final picked = await _picker.pickImage(
      source: ImageSource.camera,
      imageQuality: 85,
    );
    if (picked != null) setState(() => _attachments.add(picked));
  }

  void _removeAttachment(int index) {
    setState(() => _attachments.removeAt(index));
  }

  Future<void> _pickDate({required bool isSignedAt}) async {
    final initial = isSignedAt ? _signedAt : _startPaymentDate;
    final picked = await showDatePicker(
      context: context,
      initialDate: initial.isBefore(DateTime.now()) ? DateTime.now() : initial,
      firstDate: DateTime.now(),
      lastDate: DateTime.now().add(const Duration(days: 365)),
    );
    if (picked == null) return;
    setState(() {
      if (isSignedAt) {
        _signedAt = picked;
      } else {
        _startPaymentDate = picked;
      }
    });
  }

  Future<void> _submit() async {
    if (_attachments.isEmpty) {
      setState(
        () => _error = AppLocalizations.of(context)!.attachmentRequiredError,
      );
      return;
    }
    setState(() {
      _isSubmitting = true;
      _error = null;
    });
    try {
      await _repository.create(
        unitDepositRequestId: widget.depositRequest.id,
        signedAt: _signedAt,
        startPaymentDate: _startPaymentDate,
        attachmentPaths: _attachments.map((f) => f.path).toList(),
      );
      if (!mounted) return;
      final l10n = AppLocalizations.of(context)!;
      Get.back(result: true);
      Get.snackbar(l10n.contractRequested, l10n.contractRequestedMessage);
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
      appBar: AppBar(title: Text(l10n.requestContractTitle)),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 32),
        children: [
          AppPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  l10n.depositRequestLabel,
                  style: Theme.of(context).textTheme.labelMedium,
                ),
                Text(
                  l10n.depositRequestSummary(
                    '${widget.depositRequest.id}',
                    '${widget.depositRequest.unitId ?? '-'}',
                  ),
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                const SizedBox(height: 16),
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text(l10n.signedDate),
                  subtitle: Text(_dateFormat.format(_signedAt)),
                  trailing: const Icon(Icons.calendar_today),
                  onTap: () => _pickDate(isSignedAt: true),
                ),
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text(l10n.firstPaymentDate),
                  subtitle: Text(_dateFormat.format(_startPaymentDate)),
                  trailing: const Icon(Icons.calendar_today),
                  onTap: () => _pickDate(isSignedAt: false),
                ),
                const SizedBox(height: 8),
                Text(l10n.attachSignedContractHint),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    for (var i = 0; i < _attachments.length; i++)
                      _AttachmentThumbnail(
                        file: _attachments[i],
                        onRemove: () => _removeAttachment(i),
                      ),
                    _AddAttachmentButton(
                      icon: Icons.photo_library_outlined,
                      label: l10n.gallery,
                      onTap: _addFromGallery,
                    ),
                    _AddAttachmentButton(
                      icon: Icons.camera_alt_outlined,
                      label: l10n.camera,
                      onTap: _addFromCamera,
                    ),
                  ],
                ),
              ],
            ),
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
                : Text(l10n.submitContractRequest),
          ),
        ],
      ),
    );
  }
}

class _AttachmentThumbnail extends StatelessWidget {
  const _AttachmentThumbnail({required this.file, required this.onRemove});

  final XFile file;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        ClipRRect(
          borderRadius: BorderRadius.circular(8),
          child: Image.file(
            File(file.path),
            width: 104,
            height: 104,
            fit: BoxFit.cover,
          ),
        ),
        Positioned(
          top: 0,
          right: 0,
          child: IconButton(
            style: IconButton.styleFrom(backgroundColor: Colors.white),
            tooltip: AppLocalizations.of(context)!.cancel,
            icon: const Icon(Icons.cancel, size: 20),
            onPressed: onRemove,
          ),
        ),
      ],
    );
  }
}

class _AddAttachmentButton extends StatelessWidget {
  const _AddAttachmentButton({
    required this.icon,
    required this.label,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(8),
      child: Container(
        width: 104,
        constraints: const BoxConstraints(minHeight: 104),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          border: Border.all(color: Theme.of(context).colorScheme.outline),
          borderRadius: BorderRadius.circular(8),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon),
            const SizedBox(height: 4),
            Text(
              label,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.labelSmall,
            ),
          ],
        ),
      ),
    );
  }
}

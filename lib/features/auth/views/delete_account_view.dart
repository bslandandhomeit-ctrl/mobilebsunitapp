import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/widgets/app_ui.dart';
import '../../../l10n/app_localizations.dart';
import '../controllers/auth_controller.dart';
import '../data/auth_repository.dart';

/// Google Play account deletion: the signed-in user closes their own account after confirming the password.
class DeleteAccountView extends StatefulWidget {
  const DeleteAccountView({super.key});

  @override
  State<DeleteAccountView> createState() => _DeleteAccountViewState();
}

class _DeleteAccountViewState extends State<DeleteAccountView> {
  final _repository = AuthRepository();
  final _password = TextEditingController();
  bool _confirmed = false, _submitting = false;
  String? _error;

  Future<void> _delete() async {
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      await _repository.deleteAccount(_password.text);
      if (!mounted) return;
      final message = AppLocalizations.of(context)!.deleteAccountDone;
      await Get.find<AuthController>().signOutLocally();
      Get.snackbar('', message);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  void dispose() {
    _password.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context)!;
    final canDelete = _confirmed && _password.text.isNotEmpty && !_submitting;
    return AppPage(
      appBar: AppBar(title: Text(l.deleteAccount)),
      body: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 32),
        child: AppPanel(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                l.deleteAccountIntro,
                style: Theme.of(context).textTheme.titleSmall,
              ),
              const SizedBox(height: 12),
              Text(
                l.deleteAccountDetails,
                style: Theme.of(context).textTheme.bodyMedium,
              ),
              const SizedBox(height: 20),
              TextField(
                controller: _password,
                obscureText: true,
                onChanged: (_) => setState(() {}),
                decoration: InputDecoration(labelText: l.currentPassword),
              ),
              CheckboxListTile(
                contentPadding: EdgeInsets.zero,
                controlAffinity: ListTileControlAffinity.leading,
                value: _confirmed,
                onChanged: (v) => setState(() => _confirmed = v ?? false),
                title: Text(l.deleteAccountConfirm),
              ),
              if (_error != null) ...[
                const SizedBox(height: 4),
                Text(_error!, style: const TextStyle(color: Colors.red)),
              ],
              const SizedBox(height: 16),
              FilledButton(
                style: FilledButton.styleFrom(backgroundColor: Colors.red),
                onPressed: canDelete ? _delete : null,
                child: _submitting
                    ? const SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                      )
                    : Text(l.deleteAccountButton),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

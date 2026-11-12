import 'package:flutter/material.dart';

import 'package:get/get.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/widgets/app_ui.dart';
import '../../../l10n/app_localizations.dart';
import '../controllers/auth_controller.dart';
import '../data/auth_repository.dart';

class ChangePasswordView extends StatefulWidget {
  const ChangePasswordView({super.key, this.forced = false});

  /// First sign-in with a default password: the screen cannot be left until a new password is set.
  final bool forced;

  @override
  State<ChangePasswordView> createState() => _ChangePasswordViewState();
}

class _ChangePasswordViewState extends State<ChangePasswordView> {
  final _repository = AuthRepository();
  final _currentController = TextEditingController();
  final _newController = TextEditingController();
  bool _isSubmitting = false;
  bool _showCurrent = false, _showNew = false;
  String? _error;

  Future<void> _submit() async {
    setState(() {
      _isSubmitting = true;
      _error = null;
    });
    try {
      await _repository.changePassword(
        currentPassword: _currentController.text,
        newPassword: _newController.text,
      );
      if (!mounted) return;
      final l10n = AppLocalizations.of(context)!;
      if (widget.forced) {
        // the flag is now off on the server: reloading the user lets the app continue
        await Get.find<AuthController>().loadCurrentUser();
      } else {
        Get.back();
      }
      Get.snackbar(l10n.passwordChanged, l10n.passwordChangedMessage);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  @override
  void dispose() {
    _currentController.dispose();
    _newController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    return PopScope(
      canPop: !widget.forced,
      child: AppPage(
        appBar: AppBar(
          title: Text(l10n.changePassword),
          automaticallyImplyLeading: !widget.forced,
        ),
        body: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 32),
          child: AppPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (widget.forced) ...[
                  Text(
                    l10n.forcePasswordChangeNote,
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                  const SizedBox(height: 16),
                ],
                TextField(
                  controller: _currentController,
                  obscureText: !_showCurrent,
                  decoration: InputDecoration(
                    labelText: l10n.currentPassword,
                    suffixIcon: IconButton(
                      icon: Icon(_showCurrent
                          ? Icons.visibility_off_outlined
                          : Icons.visibility_outlined),
                      onPressed: () =>
                          setState(() => _showCurrent = !_showCurrent),
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                TextField(
                  controller: _newController,
                  obscureText: !_showNew,
                  decoration: InputDecoration(
                    labelText: l10n.newPasswordMinChars,
                    suffixIcon: IconButton(
                      icon: Icon(_showNew
                          ? Icons.visibility_off_outlined
                          : Icons.visibility_outlined),
                      onPressed: () => setState(() => _showNew = !_showNew),
                    ),
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
                      : Text(l10n.changePassword),
                ),
                if (widget.forced)
                  TextButton(
                    onPressed: _isSubmitting
                        ? null
                        : () => Get.find<AuthController>().logout(),
                    child: Text(l10n.logOut),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

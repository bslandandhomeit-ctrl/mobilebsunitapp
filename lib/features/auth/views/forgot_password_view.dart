import 'package:flutter/material.dart';

import 'package:get/get.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/widgets/app_ui.dart';
import '../../../l10n/app_localizations.dart';
import '../data/password_reset_repository.dart';

class ForgotPasswordView extends StatefulWidget {
  const ForgotPasswordView({super.key});

  @override
  State<ForgotPasswordView> createState() => _ForgotPasswordViewState();
}

enum _Step { phone, code, newPassword }

class _ForgotPasswordViewState extends State<ForgotPasswordView> {
  final _repository = PasswordResetRepository();
  final _phoneController = TextEditingController();
  final _codeController = TextEditingController();
  final _passwordController = TextEditingController();

  _Step _step = _Step.phone;
  String? _resetToken;
  bool _isSubmitting = false;
  String? _error;

  Future<void> _requestCode() async {
    setState(() {
      _isSubmitting = true;
      _error = null;
    });
    try {
      await _repository.sendSmsCode(_phoneController.text.trim());
      setState(() => _step = _Step.code);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  Future<void> _verifyCode() async {
    setState(() {
      _isSubmitting = true;
      _error = null;
    });
    try {
      final token = await _repository.verifyCode(
        phoneNumber: _phoneController.text.trim(),
        verificationCode: _codeController.text.trim(),
      );
      _resetToken = token;
      setState(() => _step = _Step.newPassword);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  Future<void> _resetPassword() async {
    setState(() {
      _isSubmitting = true;
      _error = null;
    });
    try {
      await _repository.resetPassword(
        token: _resetToken ?? '',
        password: _passwordController.text,
      );
      if (!mounted) return;
      final l10n = AppLocalizations.of(context)!;
      Get.back();
      Get.snackbar(l10n.passwordReset, l10n.passwordResetMessage);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  @override
  void dispose() {
    _phoneController.dispose();
    _codeController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    return AppPage(
      appBar: AppBar(title: Text(l10n.resetPassword)),
      body: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 32),
        child: AppPanel(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                children: List.generate(
                  3,
                  (index) => Expanded(
                    child: Container(
                      height: 4,
                      margin: const EdgeInsets.only(right: 6, bottom: 24),
                      decoration: BoxDecoration(
                        color: index <= _step.index
                            ? Theme.of(context).colorScheme.primary
                            : Theme.of(context).colorScheme.outlineVariant,
                        borderRadius: BorderRadius.circular(4),
                      ),
                    ),
                  ),
                ),
              ),
              ..._stepContent(l10n),
              if (_error != null) ...[
                const SizedBox(height: 12),
                Text(_error!, style: const TextStyle(color: Colors.red)),
              ],
            ],
          ),
        ),
      ),
    );
  }

  List<Widget> _stepContent(AppLocalizations l10n) {
    switch (_step) {
      case _Step.phone:
        return [
          Text(l10n.enterPhoneForCode),
          const SizedBox(height: 16),
          TextField(
            controller: _phoneController,
            keyboardType: TextInputType.phone,
            decoration: InputDecoration(labelText: l10n.phoneNumber),
          ),
          const SizedBox(height: 24),
          _submitButton(l10n.sendCode, _requestCode),
        ];
      case _Step.code:
        return [
          Text(l10n.enterCodeSentTo(_phoneController.text)),
          const SizedBox(height: 16),
          TextField(
            controller: _codeController,
            keyboardType: TextInputType.number,
            decoration: InputDecoration(labelText: l10n.verificationCode),
          ),
          const SizedBox(height: 24),
          _submitButton(l10n.verify, _verifyCode),
        ];
      case _Step.newPassword:
        return [
          Text(l10n.enterNewPassword),
          const SizedBox(height: 16),
          TextField(
            controller: _passwordController,
            obscureText: true,
            decoration: InputDecoration(labelText: l10n.newPassword),
          ),
          const SizedBox(height: 24),
          _submitButton(l10n.resetPassword, _resetPassword),
        ];
    }
  }

  Widget _submitButton(String label, VoidCallback onPressed) {
    return ElevatedButton(
      onPressed: _isSubmitting ? null : onPressed,
      child: _isSubmitting
          ? const SizedBox(
              width: 20,
              height: 20,
              child: CircularProgressIndicator(strokeWidth: 2),
            )
          : Text(label),
    );
  }
}

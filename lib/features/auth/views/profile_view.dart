import 'dart:io';

import 'package:flutter/material.dart';

import 'package:get/get.dart';
import 'package:image_picker/image_picker.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/widgets/app_ui.dart';
import '../../../l10n/app_localizations.dart';
import '../controllers/auth_controller.dart';
import '../data/auth_repository.dart';

/// Verified against `Api/UserController@update`: only email/gender/
/// birthdate/avatar are editable — name and phone number are not.
class ProfileView extends StatefulWidget {
  const ProfileView({super.key});

  @override
  State<ProfileView> createState() => _ProfileViewState();
}

class _ProfileViewState extends State<ProfileView> {
  final _repository = AuthRepository();
  final _emailController = TextEditingController();
  String? _gender;
  DateTime? _birthdate;
  XFile? _newAvatar;
  bool _isSubmitting = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    final user = Get.find<AuthController>().currentUser.value;
    _emailController.text = user?.email ?? '';
    _gender = (user?.gender == 'Male' || user?.gender == 'Female')
        ? user!.gender
        : null;
    _birthdate = user?.birthdate;
  }

  Future<void> _pickAvatar() async {
    final picked = await ImagePicker().pickImage(
      source: ImageSource.gallery,
      imageQuality: 85,
    );
    if (picked != null) setState(() => _newAvatar = picked);
  }

  Future<void> _pickBirthdate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _birthdate ?? DateTime(1990),
      firstDate: DateTime(1900),
      lastDate: DateTime.now(),
    );
    if (picked != null) setState(() => _birthdate = picked);
  }

  Future<void> _submit() async {
    setState(() {
      _isSubmitting = true;
      _error = null;
    });
    try {
      final updated = await _repository.updateProfile(
        email: _emailController.text.trim().isEmpty
            ? null
            : _emailController.text.trim(),
        gender: _gender,
        birthdate: _birthdate,
        avatarPath: _newAvatar?.path,
      );
      Get.find<AuthController>().currentUser.value = updated;
      if (!mounted) return;
      final l10n = AppLocalizations.of(context)!;
      Get.back();
      Get.snackbar(l10n.profileUpdated, l10n.profileUpdatedMessage);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  @override
  void dispose() {
    _emailController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final user = Get.find<AuthController>().currentUser.value;
    return AppPage(
      appBar: AppBar(title: Text(l10n.profile)),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 32),
        children: [
          Center(
            child: GestureDetector(
              onTap: _pickAvatar,
              child: CircleAvatar(
                radius: 48,
                backgroundImage: _newAvatar != null
                    ? FileImage(File(_newAvatar!.path))
                    : (user?.avatarUrl != null
                              ? NetworkImage(user!.avatarUrl!)
                              : null)
                          as ImageProvider?,
                child: _newAvatar == null && user?.avatarUrl == null
                    ? const Icon(Icons.camera_alt_outlined, size: 32)
                    : null,
              ),
            ),
          ),
          const SizedBox(height: 24),
          AppPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(l10n.name, style: Theme.of(context).textTheme.labelMedium),
                Text(
                  user?.name ?? '',
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                const SizedBox(height: 12),
                Text(
                  l10n.phoneNumber,
                  style: Theme.of(context).textTheme.labelMedium,
                ),
                Text(
                  user?.phoneNumber ?? '',
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                const SizedBox(height: 16),
              ],
            ),
          ),
          const SizedBox(height: 20),
          TextField(
            controller: _emailController,
            keyboardType: TextInputType.emailAddress,
            decoration: InputDecoration(labelText: l10n.email),
          ),
          const SizedBox(height: 16),
          DropdownButtonFormField<String>(
            isExpanded: true,
            initialValue: _gender,
            decoration: InputDecoration(labelText: l10n.gender),
            items: [
              DropdownMenuItem(value: 'Male', child: Text(l10n.male)),
              DropdownMenuItem(value: 'Female', child: Text(l10n.female)),
            ],
            onChanged: (v) => setState(() => _gender = v),
          ),
          const SizedBox(height: 16),
          ListTile(
            contentPadding: EdgeInsets.zero,
            title: Text(l10n.birthdate),
            subtitle: Text(
              _birthdate == null
                  ? l10n.notSet
                  : _birthdate!.toIso8601String().split('T').first,
            ),
            trailing: const Icon(Icons.calendar_today),
            onTap: _pickBirthdate,
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
                : Text(l10n.save),
          ),
        ],
      ),
    );
  }
}

import 'dart:async';
import 'dart:io';

import 'package:firebase_auth/firebase_auth.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';
import 'package:image_picker/image_picker.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/widgets/app_ui.dart';
import '../../../firebase_options.dart';
import '../controllers/auth_controller.dart';
import '../data/auth_repository.dart';
import '../services/customer_phone.dart';
import '../services/phone_auth_error.dart';

class RegisterView extends StatefulWidget {
  const RegisterView({super.key});

  @override
  State<RegisterView> createState() => _RegisterViewState();
}

class _RegisterViewState extends State<RegisterView> {
  final _form = GlobalKey<FormState>();
  final _firstName = TextEditingController();
  final _lastName = TextEditingController();
  final _phone = TextEditingController();
  final _email = TextEditingController();
  final _password = TextEditingController();
  final _confirm = TextEditingController();
  final _code = TextEditingController();
  DateTime? _birthdate;
  String? _gender, _avatar, _verificationId, _error;
  FirebaseAuth? _auth;
  User? _verifiedUser;
  String? _localPhone;
  bool _busy = false, _consent = false, _created = false;
  bool _verifying = false;
  int _resendSeconds = 0;
  int _attempt = 0;
  Timer? _timer;

  Future<void> _sendCode() async {
    if (!_form.currentState!.validate()) return;
    if (_birthdate == null || _gender == null || !_consent) {
      setState(
        () => _error = 'Choose your birthdate and gender, and agree to phone verification.',
      );
      return;
    }
    final attempt = ++_attempt;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      if (Firebase.apps.isEmpty) {
        await Firebase.initializeApp(
          options: DefaultFirebaseOptions.currentPlatform,
        );
      }
      _auth = FirebaseAuth.instance;
      // A previous registration attempt must not supply this attempt's identity.
      await _auth!.signOut();
      _verifiedUser = null;
      _localPhone = customerPhone(_phone.text)!;
      await _auth!.verifyPhoneNumber(
        phoneNumber: firebaseCustomerPhone(_localPhone!),
        verificationCompleted: (credential) async {
          if (!mounted || attempt != _attempt) return;
          await _verify(credential);
        },
        verificationFailed: (e) {
          if (!mounted || attempt != _attempt) return;
          setState(() {
            _error = phoneAuthError(e.code, e.message);
            _busy = false;
          });
        },
        codeSent: (id, _) {
          if (!mounted ||
              attempt != _attempt ||
              _verifying ||
              _verifiedUser != null) {
            return;
          }
          setState(() {
            _verificationId = id;
            _busy = false;
            _resendSeconds = 60;
          });
          _timer?.cancel();
          _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
            if (!mounted || _resendSeconds <= 1) {
              timer.cancel();
              if (mounted) setState(() => _resendSeconds = 0);
            } else {
              setState(() => _resendSeconds--);
            }
          });
        },
        codeAutoRetrievalTimeout: (id) {
          if (mounted &&
              attempt == _attempt &&
              !_verifying &&
              _verifiedUser == null) {
            setState(() {
              _verificationId = id;
              _busy = false;
            });
          }
        },
      );
    } on FirebaseAuthException catch (e) {
      if (mounted) {
        setState(() {
          _error = phoneAuthError(e.code, e.message);
          _busy = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = 'Phone verification is unavailable. Please try again.';
          _busy = false;
        });
      }
    }
  }

  Future<void> _verify(PhoneAuthCredential credential) async {
    if (_verifiedUser != null || _created || _verifying) return;
    _verifying = true;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final result = await _auth!.signInWithCredential(credential);
      if (!mounted) return;
      if (result.user?.phoneNumber != firebaseCustomerPhone(_localPhone!)) {
        throw StateError('Verified phone does not match.');
      }
      _verifiedUser = result.user;
      await _register();
    } on FirebaseAuthException catch (e) {
      if (mounted) {
        setState(() => _error = phoneAuthError(e.code, e.message));
      }
    } catch (_) {
      if (mounted) {
        setState(
          () => _error = 'Unable to verify your phone. Please try again.',
        );
      }
    } finally {
      _verifying = false;
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _register() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final token = await _verifiedUser!.getIdToken(true);
      await AuthRepository().registerCustomer({
        'name': '${_firstName.text.trim()} ${_lastName.text.trim()}',
        'phone_number': _localPhone,
        'email': _email.text.trim().isEmpty ? null : _email.text.trim(),
        'gender': _gender,
        'birthdate': _birthdate!.toIso8601String().split('T').first,
        'password': _password.text,
        'password_confirmation': _confirm.text,
        'firebase_id_token': token,
      }, avatarPath: _avatar);
      _created = true;
      await _auth!.signOut();
      if (!mounted) return;
      final auth = Get.find<AuthController>();
      auth.usernameController.text = _localPhone!;
      auth.passwordController.text = _password.text;
      await auth.login();
      if (mounted && auth.errorMessage.value != null) {
        setState(() => _error = 'Account created. Return to login to sign in.');
      }
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } catch (_) {
      if (mounted) {
        setState(
          () => _error = _created
              ? 'Account created. Return to login to sign in.'
              : 'Unable to create your account. Please try again.',
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _pickAvatar() async {
    final image = await ImagePicker().pickImage(
      source: ImageSource.gallery,
      maxWidth: 1024,
      maxHeight: 1024,
      imageQuality: 85,
    );
    if (image != null && mounted) setState(() => _avatar = image.path);
  }

  @override
  void dispose() {
    _attempt++;
    _timer?.cancel();
    for (final c in [
      _firstName,
      _lastName,
      _phone,
      _email,
      _password,
      _confirm,
      _code,
    ]) {
      c.dispose();
    }
    _auth?.signOut();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final codeStep = _verificationId != null || _verifiedUser != null;
    return AppPage(
      appBar: AppBar(title: const Text('Create Account')),
      body: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 560),
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: Form(
              key: _form,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Text(
                    'Customer registration',
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 16),
                  if (!codeStep) ...[
                    Center(
                      child: IconButton(
                        tooltip: 'Choose profile photo (optional)',
                        onPressed: _busy ? null : _pickAvatar,
                        icon: CircleAvatar(
                          radius: 42,
                          backgroundImage: _avatar == null
                              ? null
                              : FileImage(File(_avatar!)),
                          child: _avatar == null
                              ? const Icon(Icons.add_a_photo_outlined, size: 32)
                              : null,
                        ),
                      ),
                    ),
                    const SizedBox(height: 20),
                    _field(
                      _firstName,
                      'First name',
                      validator: (v) => v == null || v.trim().isEmpty
                          ? 'Enter your first name.'
                          : null,
                    ),
                    _field(
                      _lastName,
                      'Last name',
                      validator: (v) => v == null || v.trim().isEmpty
                          ? 'Enter your last name.'
                          : null,
                    ),
                    const SizedBox(height: 12),
                    OutlinedButton.icon(
                      onPressed: _busy
                          ? null
                          : () async {
                              final date = await showDatePicker(
                                context: context,
                                initialDate:
                                    _birthdate ??
                                    DateTime(DateTime.now().year - 18),
                                firstDate: DateTime(1900),
                                lastDate: DateTime.now(),
                              );
                              if (date != null && mounted) {
                                setState(() => _birthdate = date);
                              }
                            },
                      icon: const Icon(Icons.calendar_today_outlined),
                      label: Text(
                        _birthdate == null
                            ? 'Birthdate'
                            : _birthdate!.toIso8601String().split('T').first,
                      ),
                    ),
                    const SizedBox(height: 12),
                    DropdownButtonFormField<String>(
                      initialValue: _gender,
                      decoration: const InputDecoration(labelText: 'Gender'),
                      items: ['Male', 'Female']
                          .map(
                            (g) => DropdownMenuItem(value: g, child: Text(g)),
                          )
                          .toList(),
                      onChanged: _busy
                          ? null
                          : (v) => setState(() => _gender = v),
                      validator: (v) =>
                          v == null ? 'Choose your gender.' : null,
                    ),
                    const SizedBox(height: 12),
                    _field(
                      _phone,
                      'Phone Number',
                      keyboard: TextInputType.phone,
                      hint: '012345678 or +85512345678',
                      validator: (v) => customerPhone(v ?? '') == null
                          ? 'Enter a valid Cambodian phone number.'
                          : null,
                    ),
                    _field(
                      _email,
                      'Email (Optional)',
                      keyboard: TextInputType.emailAddress,
                      validator: (v) =>
                          v != null &&
                              v.trim().isNotEmpty &&
                              !RegExp(r'^[^\s@]+@[^\s@]+\.[^\s@]+$')
                                  .hasMatch(v.trim())
                          ? 'Enter a valid email.'
                          : null,
                    ),
                    _field(
                      _password,
                      'Password',
                      secret: true,
                      validator: (v) =>
                          v == null || v.length < 8 || v.length > 32
                          ? 'Use 8–32 characters.'
                          : null,
                    ),
                    _field(
                      _confirm,
                      'Confirm Password',
                      secret: true,
                      validator: (v) => v != _password.text
                          ? 'Passwords do not match.'
                          : null,
                    ),
                    CheckboxListTile(
                      contentPadding: EdgeInsets.zero,
                      value: _consent,
                      onChanged: _busy
                          ? null
                          : (v) => setState(() => _consent = v ?? false),
                      title: const Text(
                        'Verify my phone by SMS. Google will receive and store my phone number for spam and abuse prevention.',
                      ),
                    ),
                  ] else ...[
                    const Icon(Icons.sms_outlined, size: 48),
                    const SizedBox(height: 16),
                    Text(
                      _verifiedUser != null
                          ? 'Phone verified. Finish creating your customer account.'
                          : 'Enter the 6-digit code sent to ${firebaseCustomerPhone(_localPhone!)}.',
                    ),
                    if (_verifiedUser == null) ...[
                      const SizedBox(height: 16),
                      TextFormField(
                        controller: _code,
                        enabled: !_busy,
                        keyboardType: TextInputType.number,
                        maxLength: 6,
                        autofillHints: const [AutofillHints.oneTimeCode],
                        inputFormatters: [
                          FilteringTextInputFormatter.digitsOnly,
                        ],
                        decoration: const InputDecoration(
                          labelText: 'Verification code',
                        ),
                      ),
                      TextButton(
                        onPressed: _busy || _resendSeconds > 0
                            ? null
                            : _sendCode,
                        child: Text(
                          _resendSeconds > 0
                              ? 'Resend code in ${_resendSeconds}s'
                              : 'Resend code',
                        ),
                      ),
                      TextButton(
                        onPressed: _busy
                            ? null
                            : () {
                                _attempt++;
                                _timer?.cancel();
                                setState(() {
                                  _verificationId = null;
                                  _error = null;
                                  _code.clear();
                                });
                              },
                        child: const Text('Change phone number'),
                      ),
                    ],
                    if (_verifiedUser != null && !_created)
                      TextButton(
                        onPressed: _busy
                            ? null
                            : () {
                                _attempt++;
                                _timer?.cancel();
                                setState(() {
                                  _verifiedUser = null;
                                  _verificationId = null;
                                  _error = null;
                                  _code.clear();
                                });
                              },
                        child: const Text('Verify phone again'),
                      ),
                  ],
                  if (_error != null)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      child: Text(
                        _error!,
                        style: TextStyle(
                          color: Theme.of(context).colorScheme.error,
                        ),
                      ),
                    ),
                  const SizedBox(height: 16),
                  FilledButton(
                    onPressed: _busy || _created
                        ? null
                        : () {
                            if (_verifiedUser != null) {
                              _register();
                            } else if (!codeStep) {
                              _sendCode();
                            } else if (_code.text.length != 6) {
                              setState(
                                () => _error = 'Enter the 6-digit code.',
                              );
                            } else {
                              _verify(
                                PhoneAuthProvider.credential(
                                  verificationId: _verificationId!,
                                  smsCode: _code.text,
                                ),
                              );
                            }
                          },
                    child: _busy
                        ? const SizedBox(
                            width: 22,
                            height: 22,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : Text(codeStep ? 'Verify & Register' : 'Register'),
                  ),
                  const SizedBox(height: 12),
                  const Text(
                    'Customer accounts can browse projects and unit details.',
                    textAlign: TextAlign.center,
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _field(
    TextEditingController controller,
    String label, {
    TextInputType? keyboard,
    bool secret = false,
    String? hint,
    String? Function(String?)? validator,
  }) => Padding(
    padding: const EdgeInsets.only(bottom: 12),
    child: TextFormField(
      controller: controller,
      enabled: !_busy,
      obscureText: secret,
      keyboardType: keyboard,
      validator: validator,
      autocorrect: !secret,
      enableSuggestions: !secret,
      decoration: InputDecoration(labelText: label, hintText: hint),
    ),
  );
}

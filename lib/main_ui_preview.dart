// UI sandbox for the pre-receipt: shows the real slip with sample data, no login, no server writes.
// Run it with hot reload (edit the slip, press r) instead of making a deposit each time:
//   flutter run --flavor dev -t lib/main_ui_preview.dart
// The logos load from the local server (adb reverse tcp:8000 tcp:8000).
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:get/get.dart';

import 'app/theme/app_theme.dart';
import 'features/deposit/data/payment_qr_repository.dart';
import 'features/deposit/views/deposit_pre_receipt_view.dart';
import 'l10n/app_localizations.dart';

const _logoBase = 'http://127.0.0.1:8000/img/logo';

final _companies = <String, PaymentQr>{
  'BS Land and Home': PaymentQr(
    companyName: 'ប៊ីអេស លែន & ហូម ខូ អិលធីឌី',
    companyNameEn: 'BS Land and Home Co., Ltd',
    projectName: 'បុរីចតុមុខស៊ីធី ១',
    addressLine1: 'អគារលេខ B2-109, B2-110',
    addressLine2: 'សង្កាត់ទន្លេបាសាក់ ខណ្ឌចំការមន រាជធានីភ្នំពេញ',
    phone: '069 455555/ 099 788883',
    logoUrl: '$_logoBase/company-2.jpg',
  ),
  'East Land and Home': PaymentQr(
    companyName: 'អ៊ីស្ត លែន អេន ហូម ឯ.ក',
    companyNameEn: 'East Land and Home Co., Ltd',
    projectName: 'អ៊ីស្ត សែន សុខ ខុនដូមីនៀម',
    addressLine1: 'អគារលេខ B2-109, B2-110',
    addressLine2: 'សង្កាត់ទន្លេបាសាក់ ខណ្ឌចំការមន រាជធានីភ្នំពេញ',
    phone: '069 455555/ 099 788883',
    logoUrl: '$_logoBase/company-1.jpg',
  ),
};

const _customers = <String, String>{
  'Short name': 'លោក បាន បន្ត',
  'Long name': 'លោកស្រី ទេព កុលាប និង លោក ស៊ាង ពិសិដ្ឋ',
  'English name': 'Sopheak Emilly',
};

const _amounts = <double>[500, 49.99, 12500.5];

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(
    GetMaterialApp(
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light,
      locale: const Locale('en'),
      localizationsDelegates: const [
        AppLocalizations.delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      supportedLocales: AppLocalizations.supportedLocales,
      home: const _Sandbox(),
    ),
  );
}

class _Sandbox extends StatefulWidget {
  const _Sandbox();

  @override
  State<_Sandbox> createState() => _SandboxState();
}

class _SandboxState extends State<_Sandbox> {
  String _company = 'BS Land and Home';
  String _customer = 'Short name';
  double _amount = 500;

  Widget _pick<T>(
    String title,
    List<T> options,
    T selected,
    String Function(T) label,
    void Function(T) onPick,
  ) => Expanded(
    child: DropdownButtonHideUnderline(
      child: DropdownButton<T>(
        value: selected,
        isExpanded: true,
        isDense: true,
        style: const TextStyle(
          fontSize: 12,
          color: Colors.black87,
          fontWeight: FontWeight.w600,
        ),
        hint: Text(title),
        items: [
          for (final o in options)
            DropdownMenuItem(
              value: o,
              child: Text(label(o), overflow: TextOverflow.ellipsis),
            ),
        ],
        onChanged: (v) => setState(() => onPick(v as T)),
      ),
    ),
  );

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Column(
          children: [
            Container(
              color: const Color(0xFFEFF3FF),
              padding: const EdgeInsets.fromLTRB(12, 8, 12, 6),
              child: Row(
                children: [
                  _pick(
                    'Company',
                    _companies.keys.toList(),
                    _company,
                    (c) => c,
                    (c) => _company = c,
                  ),
                  const SizedBox(width: 10),
                  _pick(
                    'Customer',
                    _customers.keys.toList(),
                    _customer,
                    (c) => c,
                    (c) => _customer = c,
                  ),
                  const SizedBox(width: 10),
                  _pick(
                    'Amount',
                    _amounts,
                    _amount,
                    (a) => '\$$a',
                    (a) => _amount = a,
                  ),
                ],
              ),
            ),
            Expanded(
              child: ClipRect(
                child: DepositPreReceiptView(
                  data: PreReceiptData(
                    customer: _customers[_customer]!,
                    phone: '099856687',
                    unit: 'L1 R4-115',
                    project: _companies[_company]!.projectName,
                    depositAmount: _amount,
                    promotionDiscount: 3000,
                    date: DateTime(2026, 11, 11, 16, 5, 16),
                    number: '2026110005',
                    seller: 'Sopheak Emilly',
                    sellerPhone: '0234567863',
                    letterhead: _companies[_company],
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

import 'dart:typed_data';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:get/get.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:printing/printing.dart';

import '../../../core/widgets/app_ui.dart';
import '../../../l10n/app_localizations.dart';
import '../../auth/controllers/auth_controller.dart';
import '../data/payment_qr_repository.dart';

/// What the pre-receipt shows. It is not a payment receipt: the cashier's Be Loan receipt is the official one.
class PreReceiptData {
  PreReceiptData({
    required this.customer,
    required this.phone,
    required this.unit,
    required this.project,
    required this.depositAmount,
    required this.promotionDiscount,
    required this.date,
    this.number,
    this.seller,
    this.sellerPhone,
    this.letterhead,
  });

  final String customer, phone, unit;
  final String? project, number, seller, sellerPhone;
  final double depositAmount, promotionDiscount;
  final DateTime date;
  final PaymentQr? letterhead;
}

/// English words for a dollar amount, e.g. 500 -> "Five Hundred", 12.5 -> "Twelve and 50/100".
String amountInWords(double amount) {
  const ones = [
    '',
    'One',
    'Two',
    'Three',
    'Four',
    'Five',
    'Six',
    'Seven',
    'Eight',
    'Nine',
    'Ten',
    'Eleven',
    'Twelve',
    'Thirteen',
    'Fourteen',
    'Fifteen',
    'Sixteen',
    'Seventeen',
    'Eighteen',
    'Nineteen',
  ];
  const tens = [
    '',
    '',
    'Twenty',
    'Thirty',
    'Forty',
    'Fifty',
    'Sixty',
    'Seventy',
    'Eighty',
    'Ninety',
  ];
  String below1000(int n) {
    final parts = <String>[];
    if (n >= 100) {
      parts.add('${ones[n ~/ 100]} Hundred');
      n %= 100;
    }
    if (n >= 20) {
      parts.add(tens[n ~/ 10] + (n % 10 > 0 ? ' ${ones[n % 10]}' : ''));
    } else if (n > 0) {
      parts.add(ones[n]);
    }
    return parts.join(' ');
  }

  var whole = amount.floor();
  final cents = ((amount - whole) * 100).round();
  if (whole == 0 && cents == 0) return 'Zero';
  final groups = <String>[];
  const names = ['', 'Thousand', 'Million', 'Billion'];
  var i = 0;
  while (whole > 0) {
    final chunk = whole % 1000;
    if (chunk > 0) {
      groups.insert(
        0,
        '${below1000(chunk)}${names[i].isEmpty ? '' : ' ${names[i]}'}',
      );
    }
    whole ~/= 1000;
    i++;
  }
  final text = groups.join(' ');
  return cents > 0
      ? '${text.isEmpty ? 'Zero' : text} and ${cents.toString().padLeft(2, '0')}/100'
      : text;
}

/// The sale person who is logged in to the app (the Seller on the slip).
String currentSellerName() {
  if (!Get.isRegistered<AuthController>()) return '';
  final user = Get.find<AuthController>().currentUser.value;
  if (user == null) return '';
  return (user.saleName ?? '').trim().isNotEmpty
      ? user.saleName!.trim()
      : user.name.trim();
}

/// Phone number of the logged-in sale person, shown under the seller's name.
String currentSellerPhone() {
  if (!Get.isRegistered<AuthController>()) return '';
  return (Get.find<AuthController>().currentUser.value?.phoneNumber ?? '')
      .trim();
}

class DepositPreReceiptView extends StatefulWidget {
  const DepositPreReceiptView({super.key, required this.data});
  final PreReceiptData data;

  @override
  State<DepositPreReceiptView> createState() => _DepositPreReceiptViewState();
}

class _DepositPreReceiptViewState extends State<DepositPreReceiptView> {
  final _slip = GlobalKey();
  bool _busy = false;

  // Slip palette: dark ink, a calm blue for the filled-in values, red for the project, Tel and the PAID stamp.
  static const _ink = Color(0xFF1B2430);
  static const _muted = Color(0xFF6B7684);
  static const _blue = Color(0xFF1B3FC9);
  static const _red = Color(0xFFD32F2F);
  static const _line = Color(0xFFDDE2E8);

  // The slip as a one-page PDF (the slip picture on A5) so it can go to Telegram or be saved as a file.
  Future<Uint8List> _pdf() async {
    final boundary =
        _slip.currentContext!.findRenderObject() as RenderRepaintBoundary;
    final image = await boundary.toImage(pixelRatio: 4);
    final png = (await image.toByteData(format: ui.ImageByteFormat.png))!.buffer
        .asUint8List();
    final doc = pw.Document();
    final picture = pw.MemoryImage(png);
    doc.addPage(
      pw.Page(
        pageFormat: PdfPageFormat.a5,
        margin: pw.EdgeInsets.zero,
        build: (_) => pw.Image(picture, fit: pw.BoxFit.fill),
      ),
    );
    return doc.save();
  }

  String get _fileName => 'pre-receipt-${widget.data.unit}.pdf';

  // Opens the phone's share sheet with the PDF: Telegram, Messages, Drive...
  Future<void> _share() async {
    setState(() => _busy = true);
    try {
      await Printing.sharePdf(bytes: await _pdf(), filename: _fileName);
    } catch (e) {
      Get.snackbar('Share', '$e');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  // Opens the phone's print screen, where "Save as PDF" stores the file.
  Future<void> _savePdf() async {
    setState(() => _busy = true);
    try {
      final bytes = await _pdf();
      await Printing.layoutPdf(
        name: _fileName,
        format: PdfPageFormat.a5,
        onLayout: (_) async => bytes,
      );
    } catch (e) {
      Get.snackbar('PDF', '$e');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  TextStyle _t(
    double size, {
    Color color = _ink,
    FontWeight weight = FontWeight.w600,
    double? spacing,
    String? family,
  }) => TextStyle(
    fontSize: size,
    color: color,
    fontWeight: weight,
    letterSpacing: spacing,
    fontFamily: family ?? 'Manrope',
    fontFamilyFallback: const ['Noto Sans Khmer', 'Khmer Sangam MN'],
    fontFeatures: const [FontFeature.tabularFigures()],
    height: 1.5,
  );

  // Small two-language caption above a value: "Khmer  ·  English".
  Widget _caption(String khmer, String english) => Text.rich(
    TextSpan(
      children: [
        TextSpan(
          text: khmer,
          style: _t(11.5, color: _muted, weight: FontWeight.w600),
        ),
        TextSpan(
          text: '   ·   ',
          style: _t(11, color: _line, weight: FontWeight.w600),
        ),
        TextSpan(
          text: english,
          style: _t(10, color: _muted, weight: FontWeight.w600, spacing: 0.2),
        ),
      ],
    ),
  );

  // One filled-in line: caption, the value in blue, a hairline under it.
  Widget _field(
    String khmer,
    String english,
    String value, {
    double size = 15,
    InlineSpan? rich,
  }) => Padding(
    padding: const EdgeInsets.only(top: 12),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _caption(khmer, english),
        const SizedBox(height: 4),
        rich == null
            ? Text(
                value,
                style: _t(size, color: _blue, weight: FontWeight.w700),
              )
            : Text.rich(rich),
        const SizedBox(height: 5),
        Container(height: 1, color: _line),
      ],
    ),
  );

  Widget _letterhead(PaymentQr? h) {
    Widget line(
      String? text, {
      double size = 10.5,
      Color color = _muted,
      bool bold = false,
    }) => (text == null || text.trim().isEmpty)
        ? const SizedBox.shrink()
        : Padding(
            padding: const EdgeInsets.only(top: 1.5),
            child: Text(
              text,
              textAlign: TextAlign.center,
              style: _t(
                size,
                color: color,
                weight: bold ? FontWeight.w700 : FontWeight.w600,
              ),
            ),
          );
    return Row(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        if ((h?.logoUrl ?? '').isNotEmpty)
          Padding(
            padding: const EdgeInsets.only(right: 8),
            child: Image.network(
              h!.logoUrl!,
              width: 52,
              height: 52,
              fit: BoxFit.contain,
              errorBuilder: (_, _, _) => const SizedBox(width: 52, height: 52),
            ),
          ),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              line(h?.companyName, size: 17, color: _ink, bold: true),
              line(h?.companyNameEn, size: 11, color: _muted, bold: true),
              line(h?.projectName, size: 13.5, color: _red, bold: true),
              line(
                (h?.addressLine1 ?? '').isEmpty
                    ? null
                    : 'Head office: ${h!.addressLine1}',
              ),
              line(h?.addressLine2),
              line(
                (h?.phone ?? '').isEmpty ? null : 'Tel: ${h!.phone}',
                color: _red,
                bold: true,
              ),
            ],
          ),
        ),
        // Balance the logo's width so the letterhead is centered on the paper.
        if ((h?.logoUrl ?? '').isNotEmpty) const SizedBox(width: 60),
      ],
    );
  }

  // The red PAID stamp, tilted like a rubber stamp.
  Widget _paidStamp() => Transform.rotate(
    angle: -0.2,
    child: Opacity(
      opacity: 0.82,
      child: Container(
        padding: const EdgeInsets.all(3),
        decoration: BoxDecoration(
          border: Border.all(color: _red, width: 2.4),
          borderRadius: BorderRadius.circular(10),
        ),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 2),
          decoration: BoxDecoration(
            border: Border.all(color: _red, width: 1),
            borderRadius: BorderRadius.circular(7),
          ),
          child: Text(
            'PAID',
            style: _t(
              31,
              color: _red,
              weight: FontWeight.w900,
              spacing: 6,
              family: 'Manrope',
            ),
          ),
        ),
      ),
    ),
  );

  Widget _slipSheet(AppLocalizations l, PreReceiptData d) {
    final h = d.letterhead;
    String two(int v) => v.toString().padLeft(2, '0');
    final date = '${two(d.date.day)}-${two(d.date.month)}-${d.date.year}';
    final hour12 = d.date.hour % 12 == 0 ? 12 : d.date.hour % 12;
    final time =
        '${two(hour12)}:${two(d.date.minute)} ${d.date.hour >= 12 ? 'PM' : 'AM'}';
    final amount = '\$ ${d.depositAmount.toStringAsFixed(2)}';
    final cents = d.depositAmount == d.depositAmount.roundToDouble();
    final deposit =
        'Deposit ${cents ? d.depositAmount.round() : d.depositAmount}\$';
    final sellerPhone = (d.sellerPhone ?? '').trim().isNotEmpty
        ? d.sellerPhone!.trim()
        : currentSellerPhone();
    final seller = (d.seller ?? '').trim().isNotEmpty
        ? d.seller!.trim()
        : currentSellerName();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _letterhead(h),
        const SizedBox(height: 10),
        Row(
          children: [
            Container(width: 64, height: 3, color: _blue),
            Expanded(child: Container(height: 1.5, color: _line)),
          ],
        ),
        const SizedBox(height: 12),
        Text(
          'Pre-Receipt',
          textAlign: TextAlign.center,
          style: _t(
            26,
            weight: FontWeight.w800,
            family: 'Manrope',
          ),
        ),
        const SizedBox(height: 8),
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Date  $date',
                    style: _t(11, color: _ink, weight: FontWeight.w600),
                  ),
                  Text(
                    'Time  $time',
                    style: _t(11, color: _ink, weight: FontWeight.w600),
                  ),
                ],
              ),
            ),
            if ((d.number ?? '').isNotEmpty)
              Text.rich(
                TextSpan(
                  children: [
                    TextSpan(
                      text: 'លេខ / No   ',
                      style: _t(11.5, color: _muted, weight: FontWeight.w600),
                    ),
                    TextSpan(
                      text: d.number,
                      style: _t(
                        14,
                        color: _blue,
                        weight: FontWeight.w700,
                        spacing: 0.2,
                      ),
                    ),
                  ],
                ),
              ),
          ],
        ),
        Row(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Expanded(
              child: _field(
                'បានទទួលប្រាក់ពីឈ្មោះ',
                'Received From',
                d.customer,
                size: 16,
              ),
            ),
            const SizedBox(width: 12),
            Container(
              margin: const EdgeInsets.only(top: 12),
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
              decoration: BoxDecoration(
                color: const Color(0xFFEFF3FF),
                border: Border.all(color: _blue, width: 1.5),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text('ទឹកប្រាក់', style: _t(10.5, color: _muted)),
                  Text(
                    amount,
                    style: _t(
                      22,
                      color: _blue,
                      weight: FontWeight.w700,
                      family: 'Manrope',
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
        _field(
          'ទឹកប្រាក់សរសេរជាអក្សរ',
          'Amount in Words',
          amountInWords(d.depositAmount),
        ),
        _field(
          'អត្ថន័យ',
          'Description',
          '',
          rich: TextSpan(
            children: [
              TextSpan(
                text: '$deposit      ',
                style: _t(13, color: _blue, weight: FontWeight.w700),
              ),
              TextSpan(
                text: 'Property: ',
                style: _t(13, color: _ink, weight: FontWeight.w600),
              ),
              TextSpan(
                text: d.unit,
                style: _t(13, color: _blue, weight: FontWeight.w700),
              ),
            ],
          ),
        ),
        const SizedBox(height: 30),
        Row(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            SizedBox(
              width: 180,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _caption('អ្នកលក់', 'Seller'),
                  const SizedBox(height: 30),
                  Container(height: 1, color: _ink),
                  const SizedBox(height: 4),
                  Text(
                    seller,
                    style: _t(13.5, color: _blue, weight: FontWeight.w700),
                  ),
                  if (sellerPhone.isNotEmpty)
                    Padding(
                      padding: const EdgeInsets.only(top: 2),
                      child: Text(
                        'Tel: $sellerPhone',
                        style: _t(12, color: _ink, weight: FontWeight.w700),
                      ),
                    ),
                ],
              ),
            ),
            const Spacer(),
            _paidStamp(),
          ],
        ),
        const Spacer(),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context)!;
    return AppPage(
      appBar: AppBar(
        title: const Text('Pre-Receipt'),
        automaticallyImplyLeading: false,
      ),
      body: Column(
        children: [
          Expanded(
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                // The slip is an A5 portrait sheet (148 x 210 mm, 420 x 594 points): shown scaled to the screen width,
                // and captured at full size for the PDF, so the PDF is a full A5 page.
                FittedBox(
                  fit: BoxFit.fitWidth,
                  child: RepaintBoundary(
                    key: _slip,
                    child: SizedBox(
                      width: 420,
                      height: 594,
                      child: Container(
                        decoration: BoxDecoration(
                          color: Colors.white,
                          border: Border.all(color: _line, width: 1.5),
                        ),
                        padding: const EdgeInsets.fromLTRB(20, 18, 20, 16),
                        child: FittedBox(
                          fit: BoxFit.scaleDown,
                          alignment: Alignment.topCenter,
                          child: SizedBox(
                            width: 380,
                            height: 560,
                            child: _slipSheet(l, widget.data),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
          SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(20, 8, 20, 12),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed: _busy ? null : _share,
                          icon: const Icon(Icons.share),
                          label: Text(l.preReceiptShare),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed: _busy ? null : _savePdf,
                          icon: const Icon(Icons.picture_as_pdf),
                          label: FittedBox(
                            fit: BoxFit.scaleDown,
                            child: Text(l.preReceiptSavePdf),
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  FilledButton(
                    onPressed: () => Get.back(),
                    child: Text(l.preReceiptDone),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

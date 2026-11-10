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
    this.letterhead,
  });

  final String customer, phone, unit;
  final String? project, number;
  final double depositAmount, promotionDiscount;
  final DateTime date;
  final PaymentQr? letterhead;
}

/// English words for a dollar amount, e.g. 500 -> "Five Hundred", 12.5 -> "Twelve and 50/100".
String amountInWords(double amount) {
  const ones = [
    '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven',
    'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen',
  ];
  const tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
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
    if (chunk > 0) groups.insert(0, '${below1000(chunk)}${names[i].isEmpty ? '' : ' ${names[i]}'}');
    whole ~/= 1000;
    i++;
  }
  final text = groups.join(' ');
  return cents > 0 ? '${text.isEmpty ? 'Zero' : text} and ${cents.toString().padLeft(2, '0')}/100' : text;
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

  static const _blue = Color(0xFF1A1AE6);
  static const _red = Color(0xFFE53935);

  // The slip as a one-page PDF (the slip picture on A5) so it can go to Telegram or be saved as a file.
  Future<Uint8List> _pdf() async {
    final boundary = _slip.currentContext!.findRenderObject() as RenderRepaintBoundary;
    final image = await boundary.toImage(pixelRatio: 3);
    final png = (await image.toByteData(format: ui.ImageByteFormat.png))!.buffer.asUint8List();
    final doc = pw.Document();
    final picture = pw.MemoryImage(png);
    doc.addPage(
      pw.Page(
        pageFormat: PdfPageFormat.a5,
        margin: const pw.EdgeInsets.all(18),
        build: (_) => pw.Align(alignment: pw.Alignment.topCenter, child: pw.Image(picture, fit: pw.BoxFit.contain)),
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
      await Printing.layoutPdf(name: _fileName, format: PdfPageFormat.a5, onLayout: (_) async => bytes);
    } catch (e) {
      Get.snackbar('PDF', '$e');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  // "Khmer label   value ........" like the printed receipt: the value is blue with a dotted line under it.
  Widget _field(String khmer, String english, String value) => Padding(
    padding: const EdgeInsets.only(top: 10),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text.rich(
          TextSpan(
            children: [
              TextSpan(text: '$khmer  '),
              TextSpan(text: value, style: const TextStyle(color: _blue)),
            ],
          ),
          style: const TextStyle(fontSize: 14),
        ),
        const _Dotted(),
        Text(english, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700)),
      ],
    ),
  );

  // The sale person who is logged in to the app.
  String _sellerName() {
    if (!Get.isRegistered<AuthController>()) return '';
    final user = Get.find<AuthController>().currentUser.value;
    if (user == null) return '';
    return (user.saleName ?? '').trim().isNotEmpty ? user.saleName!.trim() : user.name;
  }

  Widget _head(String? text, {double size = 14, Color? color, bool bold = false}) =>
      (text == null || text.trim().isEmpty)
      ? const SizedBox.shrink()
      : Text(
          text,
          textAlign: TextAlign.center,
          style: TextStyle(fontSize: size, color: color, fontWeight: bold ? FontWeight.w800 : FontWeight.w600),
        );

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context)!;
    final d = widget.data;
    final h = d.letterhead;
    String two(int v) => v.toString().padLeft(2, '0');
    final date = '${two(d.date.day)}-${two(d.date.month)}-${d.date.year}';
    final hour12 = d.date.hour % 12 == 0 ? 12 : d.date.hour % 12;
    final time = '$date ${two(hour12)}:${two(d.date.minute)}:${two(d.date.second)} ${d.date.hour >= 12 ? 'PM' : 'AM'}';
    final amount = '\$ ${d.depositAmount.toStringAsFixed(2)}';
    final description = 'Deposit ${d.depositAmount == d.depositAmount.roundToDouble() ? d.depositAmount.round() : d.depositAmount}\$';
    return AppPage(
      appBar: AppBar(title: const Text('Pre-Receipt'), automaticallyImplyLeading: false),
      body: Column(
        children: [
          Expanded(
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                RepaintBoundary(
                  key: _slip,
                  child: Container(
                    color: Colors.white,
                    padding: const EdgeInsets.fromLTRB(16, 16, 16, 20),
                    child: DefaultTextStyle.merge(
                      style: const TextStyle(color: Colors.black87),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          if ((h?.logoUrl ?? '').isNotEmpty)
                            Center(
                              child: Image.network(
                                h!.logoUrl!,
                                height: 84,
                                errorBuilder: (_, _, _) => const SizedBox.shrink(),
                              ),
                            ),
                          _head(h?.companyName, size: 20, bold: true),
                          _head(h?.companyNameEn, size: 13, bold: true),
                          _head(h?.projectName, size: 16, color: _red, bold: true),
                          _head((h?.addressLine1 ?? '').isEmpty ? null : 'Head office: ${h!.addressLine1}', size: 12),
                          _head(h?.addressLine2, size: 12),
                          _head((h?.phone ?? '').isEmpty ? null : 'Tel: ${h!.phone}', size: 12, color: _red),
                          const SizedBox(height: 8),
                          const Divider(height: 2, thickness: 2, color: Colors.black87),
                          const SizedBox(height: 10),
                          Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text('Posting Date: $date', style: const TextStyle(color: _blue, fontWeight: FontWeight.w700, fontSize: 12)),
                                    Text('Receipt Time: $time', style: const TextStyle(color: _blue, fontWeight: FontWeight.w700, fontSize: 12)),
                                  ],
                                ),
                              ),
                              if ((d.number ?? '').isNotEmpty)
                                Text.rich(
                                  TextSpan(
                                    children: [
                                      const TextSpan(text: 'លេខ / No: '),
                                      TextSpan(text: d.number, style: const TextStyle(color: _blue, fontWeight: FontWeight.w700)),
                                    ],
                                  ),
                                  style: const TextStyle(fontSize: 12),
                                ),
                            ],
                          ),
                          const SizedBox(height: 10),
                          const Center(
                            child: Text(
                              'Pre-Receipt',
                              style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800, decoration: TextDecoration.underline),
                            ),
                          ),
                          const SizedBox(height: 10),
                          Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Expanded(child: _field('បានទទួលប្រាក់ពីឈ្មោះ:', 'Received From', d.customer)),
                              const SizedBox(width: 10),
                              Container(
                                margin: const EdgeInsets.only(top: 10),
                                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                                decoration: BoxDecoration(border: Border.all(width: 1.5)),
                                child: Column(
                                  children: [
                                    const Text('ទឹកប្រាក់', style: TextStyle(fontSize: 12)),
                                    Text(amount, style: const TextStyle(color: _blue, fontWeight: FontWeight.w800, fontSize: 15)),
                                  ],
                                ),
                              ),
                            ],
                          ),
                          _field('ទឹកប្រាក់សរសេរជាអក្សរ', 'Amount in Words', amountInWords(d.depositAmount)),
                          _field('អត្ថន័យ', 'Description', '$description     Property ${d.unit}'),
                          const SizedBox(height: 22),
                          Center(
                            child: Column(
                              children: [
                                const Text('អ្នកលក់', style: TextStyle(fontWeight: FontWeight.w700)),
                                Text.rich(
                                  TextSpan(
                                    children: [
                                      const TextSpan(text: 'Seller: '),
                                      TextSpan(
                                        text: _sellerName(),
                                        style: const TextStyle(color: _blue, fontWeight: FontWeight.w700),
                                      ),
                                    ],
                                  ),
                                ),
                                const SizedBox(height: 28),
                              ],
                            ),
                          ),
                          Text(
                            l.preReceiptFooter,
                            textAlign: TextAlign.center,
                            style: const TextStyle(fontSize: 11, color: Colors.black54),
                          ),
                        ],
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
              child: Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: _busy ? null : _share,
                      icon: const Icon(Icons.share),
                      label: Text(l.preReceiptShare),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: _busy ? null : _savePdf,
                      icon: const Icon(Icons.picture_as_pdf),
                      label: Text(l.preReceiptSavePdf),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: FilledButton(
                      onPressed: () => Get.back(),
                      child: Text(l.preReceiptDone),
                    ),
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

class _Dotted extends StatelessWidget {
  const _Dotted();

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, c) => Text(
      '.' * (c.maxWidth / 3.2).floor(),
      maxLines: 1,
      overflow: TextOverflow.clip,
      style: const TextStyle(fontSize: 10, height: 0.6, color: Colors.black45),
    ),
  );
}

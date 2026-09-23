import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_pdfview/flutter_pdfview.dart';
import 'package:path_provider/path_provider.dart';

import '../../../l10n/app_localizations.dart';
import '../data/contract_request_repository.dart';

class ContractPdfView extends StatefulWidget {
  const ContractPdfView({super.key, required this.contractId});

  final int contractId;

  @override
  State<ContractPdfView> createState() => _ContractPdfViewState();
}

class _ContractPdfViewState extends State<ContractPdfView> {
  final _repository = ContractRequestRepository();
  String? _localPath;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final bytes = await _repository.getContractPdfBytes(widget.contractId);
      final dir = await getTemporaryDirectory();
      final file = File('${dir.path}/contract_${widget.contractId}.pdf');
      await file.writeAsBytes(bytes, flush: true);
      if (mounted) setState(() => _localPath = file.path);
    } catch (_) {
      if (mounted) setState(() => _error = AppLocalizations.of(context)!.failedToLoadContractPdf);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(AppLocalizations.of(context)!.contract)),
      body: _error != null
          ? Center(child: Text(_error!))
          : _localPath == null
              ? const Center(child: CircularProgressIndicator())
              : PDFView(filePath: _localPath!),
    );
  }
}

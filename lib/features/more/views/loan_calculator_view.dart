import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../../core/widgets/app_ui.dart';
import '../../../l10n/app_localizations.dart';

/// Pure client-side calculation, no API involved. Formula matches the
/// original Android app's `Loan.java` exactly (verified 2026-09-11):
/// monthlyRate = annualInterestRate / 1200;
/// monthlyPayment = loanAmount * monthlyRate / (1 - (1 + monthlyRate)^-months)
class LoanCalculatorView extends StatefulWidget {
  const LoanCalculatorView({super.key});

  @override
  State<LoanCalculatorView> createState() => _LoanCalculatorViewState();
}

class _LoanCalculatorViewState extends State<LoanCalculatorView> {
  final _amountController = TextEditingController();
  final _interestController = TextEditingController(text: '0');
  final _monthsController = TextEditingController(text: '12');

  double? _monthlyPayment;
  double? _totalPayment;
  double? _totalInterest;

  void _calculate() {
    final amount = double.tryParse(_amountController.text.trim()) ?? 0;
    final annualInterestRate =
        double.tryParse(_interestController.text.trim()) ?? 0;
    final months = int.tryParse(_monthsController.text.trim()) ?? 0;
    if (amount <= 0 || months <= 0) {
      setState(() {
        _monthlyPayment = null;
        _totalPayment = null;
        _totalInterest = null;
      });
      return;
    }

    final monthlyRate = annualInterestRate / 1200;
    final monthlyPayment = monthlyRate == 0
        ? amount / months
        : amount * monthlyRate / (1 - math.pow(1 / (1 + monthlyRate), months));
    final totalPayment = monthlyPayment * months;

    setState(() {
      _monthlyPayment = monthlyPayment;
      _totalPayment = totalPayment;
      _totalInterest = totalPayment - amount;
    });
  }

  @override
  void dispose() {
    _amountController.dispose();
    _interestController.dispose();
    _monthsController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    return AppPage(
      appBar: AppBar(title: Text(l10n.loanCalculator)),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 32),
        children: [
          AppSectionTitle(l10n.loanCalculator),
          TextField(
            controller: _amountController,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: InputDecoration(labelText: l10n.loanAmount),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _interestController,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: InputDecoration(labelText: l10n.annualInterestRate),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _monthsController,
            keyboardType: TextInputType.number,
            decoration: InputDecoration(labelText: l10n.numberOfMonths),
          ),
          const SizedBox(height: 24),
          ElevatedButton(onPressed: _calculate, child: Text(l10n.calculate)),
          if (_monthlyPayment != null) ...[
            const SizedBox(height: 24),
            AppPanel(
              child: Column(
                children: [
                  _ResultRow(
                    label: l10n.monthlyPayment,
                    value: _monthlyPayment!,
                  ),
                  const Divider(),
                  _ResultRow(label: l10n.totalPayment, value: _totalPayment!),
                  _ResultRow(label: l10n.totalInterest, value: _totalInterest!),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _ResultRow extends StatelessWidget {
  const _ResultRow({required this.label, required this.value});

  final String label;
  final double value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label),
          const SizedBox(width: 16),
          Text(
            formatUsd(value),
            style: Theme.of(context).textTheme.titleMedium,
          ),
        ],
      ),
    );
  }
}

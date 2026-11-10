import 'dart:async';

import 'package:flutter/material.dart';

import '../../../l10n/app_localizations.dart';
import '../data/customer_repository.dart';

/// Bottom sheet that searches the customers created in Be Loan (by name, phone or national ID).
/// Returns the chosen customer, or null when closed without choosing.
Future<CustomerSuggestion?> pickCustomer(BuildContext context) {
  return showModalBottomSheet<CustomerSuggestion>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    builder: (_) => const _CustomerPickerSheet(),
  );
}

class _CustomerPickerSheet extends StatefulWidget {
  const _CustomerPickerSheet();
  @override
  State<_CustomerPickerSheet> createState() => _CustomerPickerSheetState();
}

class _CustomerPickerSheetState extends State<_CustomerPickerSheet> {
  final _repository = CustomerRepository();
  final _term = TextEditingController();
  Timer? _debounce;
  var _loading = false;
  var _searched = false;
  var _items = <CustomerSuggestion>[];

  @override
  void dispose() {
    _debounce?.cancel();
    _term.dispose();
    super.dispose();
  }

  void _onChanged(String value) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 400), () => _search(value));
  }

  Future<void> _search(String value) async {
    final term = value.trim();
    if (term.length < 2) {
      setState(() {
        _items = [];
        _searched = false;
      });
      return;
    }
    setState(() => _loading = true);
    try {
      final result = await _repository.search(term);
      if (!mounted || _term.text.trim() != term) return;
      setState(() {
        _items = result;
        _searched = true;
      });
    } catch (_) {
      if (mounted) setState(() => _searched = true);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context)!;
    final bottom = MediaQuery.of(context).viewInsets.bottom;
    return Padding(
      padding: EdgeInsets.fromLTRB(20, 0, 20, 16 + bottom),
      child: SizedBox(
        height: MediaQuery.of(context).size.height * 0.6,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              l.customerPickerTitle,
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _term,
              autofocus: true,
              onChanged: _onChanged,
              decoration: InputDecoration(
                hintText: l.customerPickerHint,
                prefixIcon: const Icon(Icons.search),
              ),
            ),
            if (_loading) const LinearProgressIndicator(),
            const SizedBox(height: 8),
            Expanded(
              child: _items.isEmpty
                  ? Center(
                      child: Text(
                        _searched ? l.customerPickerNone : '',
                        textAlign: TextAlign.center,
                      ),
                    )
                  : ListView.separated(
                      itemCount: _items.length,
                      separatorBuilder: (_, _) => const Divider(height: 1),
                      itemBuilder: (context, i) {
                        final c = _items[i];
                        final detail = [
                          if ((c.phone ?? '').isNotEmpty) c.phone,
                          if ((c.nationalId ?? '').isNotEmpty) c.nationalId,
                        ].join('  ·  ');
                        return ListTile(
                          title: Text(c.name),
                          subtitle: detail.isEmpty ? null : Text(detail),
                          onTap: () => Navigator.of(context).pop(c),
                        );
                      },
                    ),
            ),
          ],
        ),
      ),
    );
  }
}

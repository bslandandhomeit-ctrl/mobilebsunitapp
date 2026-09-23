import 'dart:async';

import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../l10n/app_localizations.dart';
import '../data/projects_repository.dart';
import '../models/unit.dart';
import 'unit_detail_view.dart';

/// Global unit-code search with debounced live results — matches the
/// original app's `AutoSuggestionActivity`.
class UnitSearchView extends StatefulWidget {
  const UnitSearchView({super.key});

  @override
  State<UnitSearchView> createState() => _UnitSearchViewState();
}

class _UnitSearchViewState extends State<UnitSearchView> {
  final _repository = ProjectsRepository();
  final _controller = TextEditingController();
  Timer? _debounce;
  List<Unit> _results = [];
  bool _isSearching = false;

  void _onChanged(String value) {
    _debounce?.cancel();
    if (value.trim().isEmpty) {
      setState(() => _results = []);
      return;
    }
    _debounce = Timer(const Duration(milliseconds: 350), () => _search(value.trim()));
  }

  Future<void> _search(String term) async {
    setState(() => _isSearching = true);
    try {
      final results = (await _repository.searchUnits(term)).items;
      if (mounted) setState(() => _results = results);
    } catch (_) {
      // Ignore — keep showing the last good results.
    } finally {
      if (mounted) setState(() => _isSearching = false);
    }
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    return Scaffold(
      appBar: AppBar(
        title: TextField(
          controller: _controller,
          autofocus: true,
          onChanged: _onChanged,
          decoration: InputDecoration(
            hintText: l10n.searchUnitCodeHint,
            border: InputBorder.none,
          ),
          style: Theme.of(context).textTheme.titleMedium,
        ),
        actions: [
          if (_isSearching)
            const Padding(
              padding: EdgeInsets.all(16),
              child: SizedBox(
                width: 16,
                height: 16,
                child: CircularProgressIndicator(strokeWidth: 2),
              ),
            ),
        ],
      ),
      body: _results.isEmpty
          ? Center(
              child: Text(
                _controller.text.trim().isEmpty ? l10n.startTypingUnitCode : l10n.noUnitsFound,
              ),
            )
          : ListView.separated(
              itemCount: _results.length,
              separatorBuilder: (_, _) => const Divider(height: 1),
              itemBuilder: (context, index) {
                final unit = _results[index];
                return ListTile(
                  title: Text(unit.name),
                  trailing: Chip(label: Text(unit.status ?? 'UNKNOWN')),
                  onTap: () => Get.to(() => UnitDetailView(unit: unit)),
                );
              },
            ),
    );
  }
}

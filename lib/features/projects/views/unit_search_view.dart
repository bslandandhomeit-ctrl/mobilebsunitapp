import 'dart:async';

import 'package:flutter/material.dart';

import 'package:get/get.dart';

import '../../../core/widgets/app_ui.dart';
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
    _debounce = Timer(
      const Duration(milliseconds: 350),
      () => _search(value.trim()),
    );
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
    return AppPage(
      appBar: AppBar(title: Text(l10n.search)),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 8, 20, 16),
            child: TextField(
              controller: _controller,
              autofocus: true,
              onChanged: _onChanged,
              decoration: InputDecoration(
                hintText: l10n.searchUnitCodeHint,
                prefixIcon: const Icon(Icons.search),
                suffixIcon: _isSearching
                    ? const Padding(
                        padding: EdgeInsets.all(14),
                        child: SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        ),
                      )
                    : null,
              ),
            ),
          ),
          Expanded(
            child: _results.isEmpty
                ? AppEmptyState(
                    message: _controller.text.trim().isEmpty
                        ? l10n.startTypingUnitCode
                        : l10n.noUnitsFound,
                    icon: Icons.search_rounded,
                  )
                : ListView.separated(
                    padding: const EdgeInsets.fromLTRB(20, 0, 20, 28),
                    itemCount: _results.length,
                    separatorBuilder: (_, _) => const SizedBox(height: 12),
                    itemBuilder: (context, index) {
                      final unit = _results[index];
                      return AppUnitTile(
                        name: unit.name,
                        status: unit.status ?? l10n.unknownStatus,
                        onTap: () => Get.to(() => UnitDetailView(unit: unit)),
                      );
                    },
                  ),
          ),
        ],
      ),
    );
  }
}

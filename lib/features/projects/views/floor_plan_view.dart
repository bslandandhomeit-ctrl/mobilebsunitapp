import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../l10n/app_localizations.dart';
import '../data/projects_repository.dart';
import '../models/project.dart';
import '../models/unit.dart';
import 'unit_detail_view.dart';

/// Despite the name (matching the original app's `units_for_plan`
/// endpoint), this shows units grouped by status rather than a visual
/// coordinate overlay — that's genuinely all the real API returns.
class FloorPlanView extends StatefulWidget {
  const FloorPlanView({super.key, required this.project});

  final Project project;

  @override
  State<FloorPlanView> createState() => _FloorPlanViewState();
}

class _FloorPlanViewState extends State<FloorPlanView> {
  final _repository = ProjectsRepository();
  Map<String, List<Unit>> _unitsByStatus = {};
  bool _isLoading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      _unitsByStatus = await _repository.getUnitsForFloorPlan(widget.project.id);
    } catch (_) {
      if (mounted) _error = AppLocalizations.of(context)!.failedToLoadUnits;
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    return Scaffold(
      appBar: AppBar(title: Text(l10n.unitsByStatusTitle(widget.project.displayName))),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error!))
              : ListView(
                  padding: const EdgeInsets.all(16),
                  children: _unitsByStatus.entries.map((entry) {
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            '${entry.key} (${entry.value.length})',
                            style: Theme.of(context).textTheme.titleMedium,
                          ),
                          const SizedBox(height: 8),
                          Wrap(
                            spacing: 8,
                            runSpacing: 8,
                            children: entry.value
                                .map(
                                  (u) => ActionChip(
                                    label: Text(u.name),
                                    onPressed: () => Get.to(() => UnitDetailView(unit: u)),
                                  ),
                                )
                                .toList(),
                          ),
                        ],
                      ),
                    );
                  }).toList(),
                ),
    );
  }
}

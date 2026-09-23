import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../l10n/app_localizations.dart';
import '../controllers/project_detail_controller.dart';
import '../models/unit.dart';
import 'floor_plan_view.dart';
import 'unit_detail_view.dart';
import 'unit_type_detail_view.dart';

class ProjectDetailView extends GetView<ProjectDetailController> {
  const ProjectDetailView({super.key});

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    return Scaffold(
      appBar: AppBar(
        title: Text(controller.project.displayName),
        actions: [
          IconButton(
            icon: const Icon(Icons.grid_view_outlined),
            tooltip: l10n.unitsByStatus,
            onPressed: () => Get.to(() => FloorPlanView(project: controller.project)),
          ),
        ],
      ),
      body: Obx(() {
        if (controller.isLoading.value && controller.units.isEmpty) {
          return const Center(child: CircularProgressIndicator());
        }
        if (controller.errorMessage.value != null && controller.units.isEmpty) {
          return Center(child: Text(controller.errorMessage.value!));
        }
        return RefreshIndicator(
          onRefresh: controller.loadUnits,
          child: ListView(
            children: [
              if (controller.project.unitTypes.isNotEmpty) ...[
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
                  child: Text(l10n.unitTypes, style: Theme.of(context).textTheme.titleLarge),
                ),
                SizedBox(
                  height: 40,
                  child: ListView.separated(
                    scrollDirection: Axis.horizontal,
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    itemCount: controller.project.unitTypes.length,
                    separatorBuilder: (_, _) => const SizedBox(width: 8),
                    itemBuilder: (context, index) {
                      final unitType = controller.project.unitTypes[index];
                      return ActionChip(
                        label: Text(unitType.displayName),
                        onPressed: () => Get.to(() => UnitTypeDetailView(unitType: unitType)),
                      );
                    },
                  ),
                ),
              ],
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
                child: Text(l10n.units, style: Theme.of(context).textTheme.titleLarge),
              ),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: TextField(
                  onChanged: (v) => controller.searchQuery.value = v,
                  decoration: InputDecoration(
                    hintText: l10n.searchByUnitCode,
                    prefixIcon: const Icon(Icons.search),
                    border: const OutlineInputBorder(),
                    isDense: true,
                  ),
                ),
              ),
              const SizedBox(height: 8),
              if (controller.filteredUnits.isEmpty)
                Padding(
                  padding: const EdgeInsets.all(24),
                  child: Center(child: Text(l10n.noUnitsFound)),
                )
              else
                ...controller.filteredUnits.map((u) => _UnitTile(unit: u)),
              if (controller.units.isNotEmpty && controller.hasMore)
                Padding(
                  padding: const EdgeInsets.all(16),
                  child: Center(
                    child: controller.isLoadingMore.value
                        ? const CircularProgressIndicator()
                        : OutlinedButton(
                            onPressed: controller.loadMore,
                            child: Text(l10n.loadMore),
                          ),
                  ),
                ),
            ],
          ),
        );
      }),
    );
  }
}

class _UnitTile extends StatelessWidget {
  const _UnitTile({required this.unit});

  final Unit unit;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      title: Text(unit.name),
      trailing: Chip(label: Text(unit.status ?? 'UNKNOWN')),
      onTap: () => Get.to(() => UnitDetailView(unit: unit)),
    );
  }
}

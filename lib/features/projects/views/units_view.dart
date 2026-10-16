import 'dart:async';

import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../core/widgets/app_ui.dart';
import '../../../l10n/app_localizations.dart';
import '../../home/controllers/home_controller.dart';
import '../data/projects_repository.dart';
import '../models/unit.dart';
import 'floor_plan_view.dart';
import 'unit_detail_view.dart';

class UnitsView extends StatefulWidget {
  const UnitsView({super.key, this.unitTypeId, this.projectId, this.title});
  final int? unitTypeId, projectId;
  final String? title;
  @override
  State<UnitsView> createState() => _UnitsViewState();
}

class _UnitsViewState extends State<UnitsView> {
  final _repository = ProjectsRepository();
  final _search = TextEditingController();
  final _scroll = ScrollController();
  final _units = <Unit>[];
  Timer? _debounce;
  int? _projectId;
  String _status = 'AVAILABLE,UNAVAILABLE';
  int _page = 1, _generation = 0;
  bool _loading = true, _loadingMore = false, _hasMore = false;
  String? _error;
  @override
  void initState() {
    super.initState();
    _projectId = widget.projectId;
    _load();
    _scroll.addListener(() {
      if (_scroll.position.extentAfter < 400 &&
          !_loading &&
          !_loadingMore &&
          _hasMore) {
        _load(more: true);
      }
    });
  }

  Future<void> _load({bool more = false}) async {
    final generation = more ? _generation : ++_generation;
    setState(() {
      _error = null;
      if (more) {
        _loadingMore = true;
      } else {
        _loading = true;
        _loadingMore = false;
      }
    });
    final page = more ? _page + 1 : 1;
    try {
      final result = await _repository.browseUnits(
        projectId: _projectId,
        unitTypeId: widget.unitTypeId,
        status: _status.isEmpty ? null : _status,
        term: _search.text.trim(),
        page: page,
      );
      if (!mounted || generation != _generation) return;
      setState(() {
        if (!more) _units.clear();
        _units.addAll(result.items);
        _page = page;
        _hasMore = page < (result.lastPage ?? page);
      });
    } catch (e) {
      if (mounted && generation == _generation) {
        setState(() => _error = e.toString());
      }
    } finally {
      if (mounted && generation == _generation) {
        setState(() {
          _loading = false;
          _loadingMore = false;
        });
      }
    }
  }

  Future<void> _filter() async {
    final l = AppLocalizations.of(context)!;
    final home = Get.find<HomeController>();
    int? project = _projectId;
    String status = _status;
    final apply = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (context) => StatefulBuilder(
        builder: (context, change) => SafeArea(
          child: Padding(
            padding: EdgeInsets.fromLTRB(
              20,
              24,
              20,
              24 + MediaQuery.viewInsetsOf(context).bottom,
            ),
            child: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  AppSectionTitle(l.filterTitle),
                  DropdownButtonFormField<int>(
                    initialValue: project,
                    isExpanded: true,
                    decoration: InputDecoration(labelText: l.project),
                    items: [
                      DropdownMenuItem<int>(
                        value: null,
                        child: Text(l.allProjectsLabel),
                      ),
                      ...home.projects.map(
                        (p) => DropdownMenuItem(
                          value: p.id,
                          child: Text(
                            p.displayName,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      ),
                    ],
                    onChanged: (v) => change(() => project = v),
                  ),
                  const SizedBox(height: 16),
                  DropdownButtonFormField<String>(
                    initialValue: status,
                    isExpanded: true,
                    decoration: InputDecoration(labelText: l.unitStatus),
                    items: [
                      DropdownMenuItem(
                        value: 'AVAILABLE,UNAVAILABLE',
                        child: Text(l.availableAndUnavailable),
                      ),
                      DropdownMenuItem(
                        value: '',
                        child: Text(l.allStatusesLabel),
                      ),
                      for (final s in [
                        'AVAILABLE',
                        'UNAVAILABLE',
                        'HOLD',
                        'DEPOSIT',
                        'CONTRACT',
                      ])
                        DropdownMenuItem(value: s, child: Text(s)),
                    ],
                    onChanged: (v) => change(() => status = v ?? ''),
                  ),
                  const SizedBox(height: 24),
                  FilledButton(
                    onPressed: () => Navigator.pop(context, true),
                    child: Text(l.applyFiltersLabel),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
    if (apply == true && mounted) {
      setState(() {
        _projectId = project;
        _status = status;
      });
      _debounce?.cancel();
      await _load();
    }
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    _scroll.dispose();
    super.dispose();
  }

  Widget _projectRows(BuildContext context) {
    final home = Get.find<HomeController>();
    final l = AppLocalizations.of(context)!;
    return Obx(() {
      final projects = home.projects
          .where((p) => _projectId == null || p.id == _projectId)
          .toList();
      if (home.isLoading.value && projects.isEmpty) {
        return const Center(child: CircularProgressIndicator());
      }
      return RefreshIndicator(
        onRefresh: home.loadHome,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.only(bottom: 24),
          children: [
            if (projects.isEmpty)
              AppEmptyState(
                message: home.errorMessage.value ?? l.noUnitsFound,
                action: TextButton(
                  onPressed: home.loadHome,
                  child: Text(l.retry),
                ),
              ),
            for (final project in projects) ...[
              Padding(
                padding: const EdgeInsets.fromLTRB(20, 8, 12, 4),
                child: Row(
                  children: [
                    Icon(
                      Icons.apartment_rounded,
                      size: 20,
                      color: Theme.of(context).colorScheme.primary,
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        project.displayName,
                        style: Theme.of(context).textTheme.titleSmall,
                      ),
                    ),
                    TextButton(
                      onPressed: () =>
                          Get.to(() => FloorPlanView(project: project)),
                      child: Text(l.floorPlanLabel),
                    ),
                  ],
                ),
              ),
              if (project.unitTypes.isEmpty)
                Padding(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 20,
                    vertical: 12,
                  ),
                  child: Text(l.noUnitsFound),
                )
              else
                SizedBox(
                  height: 164,
                  child: ListView.separated(
                    key: PageStorageKey('project-types-${project.id}'),
                    scrollDirection: Axis.horizontal,
                    padding: const EdgeInsets.symmetric(horizontal: 20),
                    itemCount: project.unitTypes.length,
                    separatorBuilder: (_, _) => const SizedBox(width: 12),
                    itemBuilder: (context, index) {
                      final type = project.unitTypes[index];
                      return SizedBox(
                        width: 220,
                        child: Material(
                          borderRadius: BorderRadius.circular(16),
                          clipBehavior: Clip.antiAlias,
                          child: InkWell(
                            onTap: () => Get.to(
                              () => UnitsView(
                                unitTypeId: type.id,
                                projectId: project.id,
                                title: type.displayName,
                              ),
                            ),
                            child: Stack(
                              fit: StackFit.expand,
                              children: [
                                AppImage(
                                  url: type.featureImageUrl,
                                  height: 164,
                                ),
                                const DecoratedBox(
                                  decoration: BoxDecoration(
                                    gradient: LinearGradient(
                                      begin: Alignment.center,
                                      end: Alignment.bottomCenter,
                                      colors: [
                                        Colors.transparent,
                                        Color(0xCC000000),
                                      ],
                                    ),
                                  ),
                                ),
                                Positioned(
                                  left: 14,
                                  right: 14,
                                  bottom: 12,
                                  child: Text(
                                    type.displayName,
                                    maxLines: 2,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(
                                      color: Colors.white,
                                      fontSize: 16,
                                      fontWeight: FontWeight.w700,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                      );
                    },
                  ),
                ),
              const SizedBox(height: 16),
            ],
          ],
        ),
      );
    });
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context)!;
    final groups = <String, List<Unit>>{};
    for (final unit in _units) {
      groups.putIfAbsent(unit.projectName ?? l.project, () => []).add(unit);
    }
    return AppPage(
      appBar: AppBar(title: Text(widget.title ?? l.units)),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 4, 20, 12),
            child: Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: _search,
                    onChanged: (_) {
                      _debounce?.cancel();
                      _debounce = Timer(
                        const Duration(milliseconds: 350),
                        _load,
                      );
                    },
                    decoration: InputDecoration(
                      isDense: true,
                      hintText: l.searchByUnitCode,
                      prefixIcon: const Icon(Icons.search, size: 20),
                      contentPadding: const EdgeInsets.all(12),
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                IconButton.filledTonal(
                  onPressed: _filter,
                  tooltip: l.filterTitle,
                  icon: const Icon(Icons.tune_rounded),
                ),
              ],
            ),
          ),
          Expanded(
            child:
                widget.unitTypeId == null &&
                    _search.text.trim().isEmpty &&
                    _status == 'AVAILABLE,UNAVAILABLE'
                ? _projectRows(context)
                : _loading
                ? const Center(child: CircularProgressIndicator())
                : RefreshIndicator(
                    onRefresh: _load,
                    child: ListView(
                      controller: _scroll,
                      physics: const AlwaysScrollableScrollPhysics(),
                      padding: const EdgeInsets.fromLTRB(20, 4, 20, 24),
                      children: [
                        if (_error != null)
                          AppEmptyState(
                            message: _error!,
                            action: TextButton(
                              onPressed: _load,
                              child: Text(l.retry),
                            ),
                          ),
                        if (_units.isEmpty && _error == null)
                          AppEmptyState(message: l.noUnitsFound),
                        for (final entry in groups.entries) ...[
                          Padding(
                            padding: const EdgeInsets.only(top: 8, bottom: 4),
                            child: AppSectionTitle(entry.key),
                          ),
                          for (final unit in entry.value)
                            Padding(
                              padding: const EdgeInsets.only(bottom: 12),
                              child: Card(
                                child: InkWell(
                                  borderRadius: BorderRadius.circular(20),
                                  onTap: () async {
                                    await Get.to(
                                      () => UnitDetailView(unit: unit),
                                    );
                                    if (mounted) _load();
                                  },
                                  child: Padding(
                                    padding: const EdgeInsets.all(12),
                                    child: Row(
                                      children: [
                                        ClipRRect(
                                          borderRadius: BorderRadius.circular(
                                            12,
                                          ),
                                          child: SizedBox(
                                            width: 76,
                                            height: 90,
                                            child: AppImage(
                                              url: unit.previewUrl,
                                              height: 90,
                                            ),
                                          ),
                                        ),
                                        const SizedBox(width: 14),
                                        Expanded(
                                          child: Column(
                                            crossAxisAlignment:
                                                CrossAxisAlignment.start,
                                            children: [
                                              Text(
                                                unit.code,
                                                style: Theme.of(context)
                                                    .textTheme
                                                    .titleSmall,
                                              ),
                                              const SizedBox(height: 4),
                                              Text(
                                                unit.unitTypeName ?? 'N/A',
                                                style: Theme.of(context)
                                                    .textTheme
                                                    .bodySmall,
                                              ),
                                              const SizedBox(height: 8),
                                              Text(
                                                unit.price == null
                                                    ? 'N/A'
                                                    : formatUsd(unit.price!),
                                                style: TextStyle(
                                                  color: Theme.of(context)
                                                      .colorScheme
                                                      .primary,
                                                  fontWeight: FontWeight.w700,
                                                ),
                                              ),
                                              const SizedBox(height: 8),
                                              AppStatusBadge(
                                                unit.status ?? l.unknownStatus,
                                              ),
                                            ],
                                          ),
                                        ),
                                        const Icon(
                                          Icons.chevron_right,
                                          size: 20,
                                        ),
                                      ],
                                    ),
                                  ),
                                ),
                              ),
                            ),
                        ],
                        if (_loadingMore)
                          const Center(
                            child: Padding(
                              padding: EdgeInsets.all(16),
                              child: CircularProgressIndicator(),
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

import 'package:flutter/material.dart';

import '../../../core/widgets/app_ui.dart';
import '../../../l10n/app_localizations.dart';
import '../models/unit.dart';

class UnitInformationCard extends StatelessWidget {
  const UnitInformationCard({
    super.key,
    required this.unit,
    this.showFeatures = false,
  });
  final Unit unit;
  final bool showFeatures;
  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context)!;
    String count(double? n) => n == null || n <= 0
        ? 'N/A'
        : n == n.roundToDouble()
        ? n.toStringAsFixed(0)
        : n.toString();
    String size(double? w, double? h, double? area) =>
        w != null && h != null && w > 0 && h > 0
        ? '${count(w)} × ${count(h)} m'
        : area != null && area > 0
        ? '${count(area)} m²'
        : 'N/A';
    final values = <(String, String)>[
      (l.unitCode, unit.code),
      (l.project, unit.projectName ?? 'N/A'),
      (l.unitType, unit.unitTypeName ?? 'N/A'),
      (l.unitPriceLabel, unit.price == null ? 'N/A' : formatUsd(unit.price!)),
      if (showFeatures) ...[
        (
          l.houseSizeLabel,
          size(unit.buildingWidth, unit.buildingLength, unit.buildingArea),
        ),
        (l.landSizeLabel, size(unit.landWidth, unit.landLength, unit.landArea)),
        (l.floorLabel, unit.floor?.isNotEmpty == true ? unit.floor! : 'N/A'),
        (l.bedroomsLabel, count(unit.bedrooms)),
        (l.bathroomsLabel, count(unit.bathrooms)),
        (l.livingRoomsLabel, count(unit.livingRooms)),
        (l.kitchensLabel, count(unit.kitchens)),
        (l.swimmingPoolsLabel, count(unit.swimmingPools)),
      ],
    ];
    return AppPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          AppSectionTitle(l.unitInformationTitle),
          for (final entry in values.take(4))
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 9),
              child: LayoutBuilder(
                builder: (context, constraints) => constraints.maxWidth < 300
                    ? Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            entry.$1,
                            style: Theme.of(context).textTheme.labelMedium,
                          ),
                          const SizedBox(height: 4),
                          Text(
                            entry.$2,
                            style: const TextStyle(fontWeight: FontWeight.w600),
                          ),
                        ],
                      )
                    : Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Expanded(
                            child: Text(
                              entry.$1,
                              style: Theme.of(context).textTheme.labelMedium,
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Text(
                              entry.$2,
                              textAlign: TextAlign.end,
                              style: const TextStyle(
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ),
                        ],
                      ),
              ),
            ),
          if (showFeatures)
            Padding(
              padding: const EdgeInsets.only(top: 16),
              child: LayoutBuilder(
                builder: (context, constraints) => Wrap(
                  spacing: 12,
                  runSpacing: 12,
                  children: [
                    for (final entry in values.skip(4))
                      Container(
                        width: (constraints.maxWidth - 12) / 2,
                        padding: const EdgeInsets.all(14),
                        decoration: BoxDecoration(
                          color: Theme.of(context)
                              .colorScheme
                              .surfaceContainerLow,
                          borderRadius: BorderRadius.circular(14),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              entry.$1,
                              style: Theme.of(context).textTheme.bodySmall,
                            ),
                            const SizedBox(height: 8),
                            Text(
                              entry.$2,
                              style: Theme.of(context).textTheme.titleMedium,
                            ),
                          ],
                        ),
                      ),
                  ],
                ),
              ),
            ),
          if (!showFeatures)
            Padding(
              padding: const EdgeInsets.only(top: 12),
              child: Text(
                l.contractPriceNote,
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ),
        ],
      ),
    );
  }
}

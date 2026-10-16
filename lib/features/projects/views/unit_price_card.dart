import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../../core/widgets/app_ui.dart';
import '../../../l10n/app_localizations.dart';
import '../models/unit.dart';

/// Price at the top of the unit detail: with a running promotion the promotion
/// price is large, the normal price is crossed out, and "Valid until" shows the
/// end of this month (or the promotion's end date when that comes first).
class UnitPriceCard extends StatelessWidget {
  const UnitPriceCard({super.key, required this.unit});
  final Unit unit;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context)!;
    final theme = Theme.of(context);
    final promotionPrice = unit.promotionPrice;
    final validUntil = unit.promotionValidUntil();
    final big = theme.textTheme.headlineMedium?.copyWith(
      fontWeight: FontWeight.w800,
      color: promotionPrice == null ? null : const Color(0xFFD32F2F),
    );
    return AppPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AppSectionTitle(l.priceTitle),
          if (unit.price == null)
            Text('N/A', style: big)
          else if (promotionPrice == null)
            Text(formatUsd(unit.price!), style: big)
          else ...[
            Text(l.promotionPriceLabel, style: theme.textTheme.labelMedium),
            const SizedBox(height: 4),
            Text(formatUsd(promotionPrice), style: big),
            const SizedBox(height: 6),
            Wrap(
              spacing: 10,
              runSpacing: 6,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: [
                Text(
                  formatUsd(unit.price!),
                  style: theme.textTheme.titleMedium?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
                    decoration: TextDecoration.lineThrough,
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 8,
                    vertical: 3,
                  ),
                  decoration: BoxDecoration(
                    color: const Color(0xFFD32F2F),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text(
                    l.promotionDiscountLabel(
                      '-${formatUsdWhole(unit.promotionAmount!)}',
                    ),
                    style: const TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w700,
                      fontSize: 12,
                    ),
                  ),
                ),
              ],
            ),
            if (validUntil != null) ...[
              const SizedBox(height: 10),
              Row(
                children: [
                  Icon(
                    Icons.schedule_rounded,
                    size: 16,
                    color: theme.colorScheme.primary,
                  ),
                  const SizedBox(width: 6),
                  Text(
                    l.promotionValidUntil(
                      DateFormat('d MMM yyyy').format(validUntil),
                    ),
                    style: theme.textTheme.bodyMedium?.copyWith(
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ),
            ],
          ],
        ],
      ),
    );
  }
}

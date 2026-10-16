import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../app/theme/app_theme.dart';

final _usdFormat = NumberFormat.currency(
  locale: 'en_US',
  symbol: '\$',
  decimalDigits: 2,
);
String formatUsd(num value) => _usdFormat.format(value);

final _usdWholeFormat = NumberFormat.currency(symbol: r'$', decimalDigits: 0);

/// Whole dollars, for short labels such as a discount ribbon: 3000 -> "$3,000".
String formatUsdWhole(num value) => _usdWholeFormat.format(value);

/// Shared safe area and comfortable reading width for phone/tablet screens.
class AppPage extends StatelessWidget {
  const AppPage({
    super.key,
    this.appBar,
    this.bottomNavigationBar,
    required this.body,
  });
  final PreferredSizeWidget? appBar;
  final Widget body;
  final Widget? bottomNavigationBar;
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: appBar,
    bottomNavigationBar: bottomNavigationBar,
    body: SafeArea(
      top: appBar == null,
      child: Align(
        alignment: Alignment.topCenter,
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 760),
          child: body,
        ),
      ),
    ),
  );
}

class AppPanel extends StatelessWidget {
  const AppPanel({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(20),
  });
  final Widget child;
  final EdgeInsetsGeometry padding;
  @override
  Widget build(BuildContext context) => Card(
    child: Padding(padding: padding, child: child),
  );
}

class AppSectionTitle extends StatelessWidget {
  const AppSectionTitle(this.title, {super.key, this.count});
  final String title;
  final int? count;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 16),
    child: Row(
      children: [
        Expanded(
          child: Text(title, style: Theme.of(context).textTheme.titleMedium),
        ),
        if (count != null)
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
            decoration: BoxDecoration(
              color: Theme.of(context).colorScheme.primaryContainer,
              borderRadius: BorderRadius.circular(8),
            ),
            child: Text(
              '$count',
              style: TextStyle(
                color: Theme.of(context).colorScheme.onPrimaryContainer,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
      ],
    ),
  );
}

class AppIconTile extends StatelessWidget {
  const AppIconTile(this.icon, {super.key, this.size = 48});
  final IconData icon;
  final double size;
  @override
  Widget build(BuildContext context) => Container(
    width: size,
    height: size,
    decoration: BoxDecoration(
      color: Theme.of(context).colorScheme.primaryContainer,
      borderRadius: BorderRadius.circular(14),
    ),
    child: Icon(icon, color: AppTheme.brand, size: size * .48),
  );
}

class AppStatusBadge extends StatelessWidget {
  const AppStatusBadge(this.status, {super.key});
  final String status;
  @override
  Widget build(BuildContext context) {
    final color = switch (status.toUpperCase()) {
      'AVAILABLE' || 'APPROVED' => const Color(0xFF176449),
      'PENDING' || 'HOLD' || 'OVERDUE' => const Color(0xFF875600),
      'REJECTED' || 'CANCELLED' || 'CANCELED' => const Color(0xFFB42338),
      'DEPOSIT' || 'CONTRACT' => AppTheme.brand,
      _ => AppTheme.muted,
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: color.withValues(alpha: .08),
        borderRadius: BorderRadius.circular(9),
      ),
      child: Text(
        status,
        style: TextStyle(
          color: color,
          fontSize: 12,
          fontWeight: FontWeight.w700,
          height: 1.5,
        ),
      ),
    );
  }
}

/// Actions sit below details to avoid narrow trailing columns and overflow.
class AppRequestCard extends StatelessWidget {
  const AppRequestCard({
    super.key,
    required this.title,
    required this.status,
    this.actions,
    this.summary,
    this.onTap,
  });
  final String title;
  final String status;
  final Widget? actions;
  final Widget? summary;
  final VoidCallback? onTap;
  @override
  Widget build(BuildContext context) => InkWell(
    borderRadius: BorderRadius.circular(20),
    onTap: onTap,
    child: AppPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const AppIconTile(Icons.description_outlined),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title, style: Theme.of(context).textTheme.titleMedium),
                    const SizedBox(height: 8),
                    AppStatusBadge(status),
                  ],
                ),
              ),
            ],
          ),
          if (summary != null) ...[const SizedBox(height: 16), summary!],
          if (actions != null) ...[
            const Divider(height: 28),
            Align(alignment: Alignment.centerRight, child: actions!),
          ],
        ],
      ),
    ),
  );
}

class AppEmptyState extends StatelessWidget {
  const AppEmptyState({
    super.key,
    required this.message,
    this.icon = Icons.inbox_outlined,
    this.action,
  });
  final String message;
  final IconData icon;
  final Widget? action;
  @override
  Widget build(BuildContext context) => Center(
    child: SingleChildScrollView(
      padding: const EdgeInsets.all(32),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          AppIconTile(icon, size: 64),
          const SizedBox(height: 20),
          Text(
            message,
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.bodyLarge,
          ),
          if (action != null) ...[const SizedBox(height: 16), action!],
        ],
      ),
    ),
  );
}

class AppImage extends StatelessWidget {
  const AppImage({
    super.key,
    this.url,
    this.height = 180,
    this.icon = Icons.apartment_rounded,
    this.fit = BoxFit.cover,
  });
  final String? url;
  final double height;
  final IconData icon;
  final BoxFit fit;
  Widget _placeholder() => Container(
    height: height,
    width: double.infinity,
    decoration: const BoxDecoration(
      gradient: LinearGradient(
        colors: [Color(0xFFE6F2FA), Color(0xFFD6E7F3)],
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
      ),
    ),
    child: Icon(icon, size: 40, color: AppTheme.brand.withValues(alpha: .65)),
  );
  @override
  Widget build(BuildContext context) => url == null || url!.isEmpty
      ? _placeholder()
      : CachedNetworkImage(
          imageUrl: url!,
          height: height,
          width: double.infinity,
          fit: fit,
          placeholder: (_, _) => _placeholder(),
          errorWidget: (_, _, _) => _placeholder(),
        );
}

class AppUnitTile extends StatelessWidget {
  const AppUnitTile({
    super.key,
    required this.name,
    required this.status,
    required this.onTap,
  });
  final String name;
  final String status;
  final VoidCallback onTap;
  @override
  Widget build(BuildContext context) => Card(
    child: InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Row(
          children: [
            const AppIconTile(Icons.home_work_outlined),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(name, style: Theme.of(context).textTheme.titleMedium),
                  const SizedBox(height: 8),
                  AppStatusBadge(status),
                ],
              ),
            ),
            const SizedBox(width: 8),
            const Icon(Icons.chevron_right_rounded, color: AppTheme.muted),
          ],
        ),
      ),
    ),
  );
}

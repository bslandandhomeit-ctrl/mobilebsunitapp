import 'package:flutter/material.dart';

import '../../features/home/views/home_view.dart';
import '../../features/more/views/more_view.dart';
import '../../l10n/app_localizations.dart';

/// Bottom-nav shell. The original app split these into separate top-level
/// activities (home, notification, post, hold, deposit, contract_request,
/// more) — those now live under More until we know the real nav layout.
class MainShell extends StatefulWidget {
  const MainShell({super.key});

  @override
  State<MainShell> createState() => _MainShellState();
}

class _MainShellState extends State<MainShell> {
  int _index = 0;

  static const _pages = [HomeView(), MoreView()];

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    return Scaffold(
      body: IndexedStack(index: _index, children: _pages),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _index,
        onDestinationSelected: (i) => setState(() => _index = i),
        destinations: [
          NavigationDestination(
            icon: const Icon(Icons.home_outlined),
            selectedIcon: const Icon(Icons.home),
            label: l10n.home,
          ),
          NavigationDestination(
            icon: const Icon(Icons.menu_outlined),
            selectedIcon: const Icon(Icons.menu),
            label: l10n.more,
          ),
        ],
      ),
    );
  }
}

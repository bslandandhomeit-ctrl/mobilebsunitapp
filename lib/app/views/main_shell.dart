import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../features/projects/views/units_view.dart';
import '../../features/hold/controllers/hold_controller.dart';
import '../../features/hold/views/hold_view.dart';
import '../../features/deposit/controllers/deposit_controller.dart';
import '../../features/deposit/views/deposit_view.dart';
import '../../features/contract_request/controllers/contract_request_controller.dart';
import '../../features/contract_request/views/contract_request_view.dart';
import '../../features/more/views/more_view.dart';
import '../../l10n/app_localizations.dart';

class MainShell extends StatefulWidget {
  const MainShell({super.key});
  @override
  State<MainShell> createState() => _MainShellState();
}

class _MainShellState extends State<MainShell> {
  int _index = 0;
  final _visited = <int>{0};
  static const _pages = [
    UnitsView(),
    HoldView(),
    DepositView(),
    ContractRequestView(),
    MoreView(),
  ];
  @override
  void initState() {
    super.initState();
    if (!Get.isRegistered<HoldController>()) {
      Get.lazyPut(HoldController.new, fenix: true);
    }
    if (!Get.isRegistered<DepositController>()) {
      Get.lazyPut(DepositController.new, fenix: true);
    }
    if (!Get.isRegistered<ContractRequestController>()) {
      Get.lazyPut(ContractRequestController.new, fenix: true);
    }
  }

  void _select(int index) {
    if (_index == index) return;
    final visited = _visited.contains(index);
    setState(() {
      _index = index;
      _visited.add(index);
    });
    if (visited && index == 1) Get.find<HoldController>().load();
    if (visited && index == 2) Get.find<DepositController>().load();
    if (visited && index == 3) Get.find<ContractRequestController>().load();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context)!;
    final labels = [
      l.units,
      l.holdTabLabel,
      l.depositTabLabel,
      l.contractTabLabel,
      l.more,
    ];
    final icons = [
      Icons.home_rounded,
      Icons.lock_clock_outlined,
      Icons.payments_outlined,
      Icons.description_outlined,
      Icons.settings_rounded,
    ];
    return Scaffold(
      body: IndexedStack(
        index: _index,
        children: [
          for (var i = 0; i < _pages.length; i++)
            AnimatedSlide(
              duration: const Duration(milliseconds: 240),
              curve: Curves.easeOutCubic,
              offset: i == _index ? Offset.zero : const Offset(.035, 0),
              child: AnimatedOpacity(
                duration: const Duration(milliseconds: 220),
                opacity: i == _index ? 1 : 0,
                child: _visited.contains(i)
                    ? _pages[i]
                    : const SizedBox.shrink(),
              ),
            ),
        ],
      ),
      bottomNavigationBar: DecoratedBox(
        decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.surface,
          border: Border(
            top: BorderSide(
              color: Theme.of(context).dividerColor.withValues(alpha: .08),
            ),
          ),
          boxShadow: const [
            BoxShadow(
              color: Color(0x08000000),
              blurRadius: 18,
              offset: Offset(0, -4),
            ),
          ],
        ),
        child: Material(
          color: Colors.transparent,
          child: SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
              child: LayoutBuilder(
                builder: (context, constraints) {
                  final inactiveWidth = (constraints.maxWidth - 132) / 4;
                  return Row(
                    children: [
                      for (var i = 0; i < labels.length; i++)
                        TweenAnimationBuilder<double>(
                          duration: const Duration(milliseconds: 280),
                          curve: Curves.easeInOutCubic,
                          tween: Tween(end: _index == i ? 132 : inactiveWidth),
                          builder: (context, width, _) => SizedBox(
                            width: width,
                            child: Semantics(
                              selected: _index == i,
                              button: true,
                              label: labels[i],
                              child: Tooltip(
                                message: labels[i],
                                child: Padding(
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 2,
                                  ),
                                  child: InkWell(
                                    borderRadius: BorderRadius.circular(16),
                                    onTap: () => _select(i),
                                    child: AnimatedContainer(
                                      duration: const Duration(
                                        milliseconds: 240,
                                      ),
                                      height: 56,
                                      padding: const EdgeInsets.symmetric(
                                        horizontal: 4,
                                      ),
                                      decoration: BoxDecoration(
                                        borderRadius: BorderRadius.circular(16),
                                        color: _index == i
                                            ? const Color(0xFFE3F4FE)
                                            : Colors.transparent,
                                      ),
                                      child: Row(
                                        mainAxisAlignment:
                                            MainAxisAlignment.center,
                                        children: [
                                          AnimatedScale(
                                            scale: _index == i ? 1.05 : 1,
                                            duration: const Duration(
                                              milliseconds: 240,
                                            ),
                                            child: Icon(
                                              icons[i],
                                              size: 23,
                                              color: _index == i
                                                  ? const Color(0xFF2389C4)
                                                  : const Color(0xFF929BA5),
                                            ),
                                          ),
                                          if (_index == i) ...[
                                            const SizedBox(width: 6),
                                            Flexible(
                                              child: Text(
                                                labels[i],
                                                maxLines: 2,
                                                overflow: TextOverflow.ellipsis,
                                                style: const TextStyle(
                                                  fontSize: 12,
                                                  height: 1.15,
                                                  fontWeight: FontWeight.w600,
                                                  color: Color(0xFF2389C4),
                                                ),
                                              ),
                                            ),
                                          ],
                                        ],
                                      ),
                                    ),
                                  ),
                                ),
                              ),
                            ),
                          ),
                        ),
                    ],
                  );
                },
              ),
            ),
          ),
        ),
      ),
    );
  }
}

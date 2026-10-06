import 'package:flutter/material.dart';

import 'package:get/get.dart';

import '../../auth/controllers/auth_controller.dart';
import '../../auth/views/change_password_view.dart';
import '../../auth/views/members_view.dart';
import '../../auth/views/profile_view.dart';
import '../../contract_request/controllers/contract_request_controller.dart';
import '../../contract_request/views/contract_request_view.dart';
import '../../deposit/controllers/deposit_controller.dart';
import '../../deposit/views/deposit_view.dart';
import '../../hold/controllers/hold_controller.dart';
import '../../hold/views/hold_view.dart';
import '../../notification/controllers/notification_controller.dart';
import '../../notification/views/notification_view.dart';
import '../../post/controllers/post_controller.dart';
import '../../post/views/post_view.dart';
import 'about_view.dart';
import 'document_scanner_view.dart';
import 'loan_calculator_view.dart';
import 'support_center_view.dart';
import '../../../core/widgets/app_ui.dart';
import '../../../l10n/app_localizations.dart';

class MoreView extends StatelessWidget {
  const MoreView({super.key});

  @override
  Widget build(BuildContext context) {
    final auth = Get.find<AuthController>();
    final l10n = AppLocalizations.of(context)!;
    return AppPage(
      appBar: AppBar(title: Text(l10n.more)),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 28),
        children: [
          AppPanel(
            padding: EdgeInsets.zero,
            child: Column(
              children: [
                Obx(() {
                  final user = auth.currentUser.value;
                  return _MenuTile(
                    leading: const CircleAvatar(child: Icon(Icons.person)),
                    title: Text(user?.name ?? l10n.profile),
                    subtitle: Text(user?.phone ?? user?.email ?? ''),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () => Get.to(() => const ProfileView()),
                  );
                }),
              ],
            ),
          ),
          const SizedBox(height: 16),
          AppPanel(
            padding: EdgeInsets.zero,
            child: Column(
              children: [
                _MenuTile(
                  leading: const Icon(Icons.lock_outline),
                  title: Text(l10n.changePassword),
                  onTap: () => Get.to(() => const ChangePasswordView()),
                ),
                _MenuTile(
                  leading: const Icon(Icons.people_outline),
                  title: Text(l10n.members),
                  onTap: () => Get.to(() => const MembersView()),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          AppPanel(
            padding: EdgeInsets.zero,
            child: Column(
              children: [
                _MenuTile(
                  leading: const Icon(Icons.notifications_outlined),
                  title: Text(l10n.notifications),
                  onTap: () => Get.to(
                    () => const NotificationView(),
                    binding: BindingsBuilder(() {
                      Get.put(NotificationController());
                    }),
                  ),
                ),
                _MenuTile(
                  leading: const Icon(Icons.campaign_outlined),
                  title: Text(l10n.announcements),
                  onTap: () => Get.to(
                    () => const PostView(),
                    binding: BindingsBuilder(() {
                      Get.put(PostController());
                    }),
                  ),
                ),
                _MenuTile(
                  leading: const Icon(Icons.pan_tool_outlined),
                  title: Text(l10n.holdRequests),
                  onTap: () => Get.to(
                    () => const HoldView(),
                    binding: BindingsBuilder(() {
                      Get.put(HoldController());
                    }),
                  ),
                ),
                _MenuTile(
                  leading: const Icon(Icons.savings_outlined),
                  title: Text(l10n.depositRequests),
                  onTap: () => Get.to(
                    () => const DepositView(),
                    binding: BindingsBuilder(() {
                      Get.put(DepositController());
                    }),
                  ),
                ),
                _MenuTile(
                  leading: const Icon(Icons.description_outlined),
                  title: Text(l10n.contractRequests),
                  onTap: () => Get.to(
                    () => const ContractRequestView(),
                    binding: BindingsBuilder(() {
                      Get.put(ContractRequestController());
                    }),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          AppPanel(
            padding: EdgeInsets.zero,
            child: Column(
              children: [
                _MenuTile(
                  leading: const Icon(Icons.document_scanner_outlined),
                  title: Text(l10n.documentScanner),
                  onTap: () => Get.to(() => const DocumentScannerView()),
                ),
                _MenuTile(
                  leading: const Icon(Icons.calculate_outlined),
                  title: Text(l10n.loanCalculator),
                  onTap: () => Get.to(() => const LoanCalculatorView()),
                ),
                _MenuTile(
                  leading: const Icon(Icons.support_agent_outlined),
                  title: Text(l10n.supportCenter),
                  onTap: () => Get.to(() => const SupportCenterView()),
                ),
                _MenuTile(
                  leading: const Icon(Icons.info_outline),
                  title: Text(l10n.aboutApp),
                  onTap: () => Get.to(() => const AboutView()),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          AppPanel(
            padding: EdgeInsets.zero,
            child: Column(
              children: [
                _MenuTile(
                  leading: const Icon(Icons.logout),
                  title: Text(l10n.logOut),
                  onTap: auth.logout,
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
        ],
      ),
    );
  }
}

class _MenuTile extends StatelessWidget {
  const _MenuTile({
    required this.leading,
    required this.title,
    this.subtitle,
    this.trailing,
    this.onTap,
  });
  final Widget leading;
  final Widget title;
  final Widget? subtitle;
  final Widget? trailing;
  final VoidCallback? onTap;
  @override
  Widget build(BuildContext context) => ListTile(
    leading: Container(
      width: 44,
      height: 44,
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.primaryContainer,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Center(child: leading),
    ),
    title: title,
    subtitle: subtitle,
    trailing: trailing ?? const Icon(Icons.chevron_right_rounded, size: 20),
    onTap: onTap,
  );
}

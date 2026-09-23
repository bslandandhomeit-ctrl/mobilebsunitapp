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
import '../../../l10n/app_localizations.dart';

class MoreView extends StatelessWidget {
  const MoreView({super.key});

  @override
  Widget build(BuildContext context) {
    final auth = Get.find<AuthController>();
    final l10n = AppLocalizations.of(context)!;
    return Scaffold(
      appBar: AppBar(title: Text(l10n.more)),
      body: ListView(
        children: [
          Obx(() {
            final user = auth.currentUser.value;
            return ListTile(
              leading: const CircleAvatar(child: Icon(Icons.person)),
              title: Text(user?.name ?? l10n.profile),
              subtitle: Text(user?.phone ?? user?.email ?? ''),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => Get.to(() => const ProfileView()),
            );
          }),
          const Divider(),
          ListTile(
            leading: const Icon(Icons.lock_outline),
            title: Text(l10n.changePassword),
            onTap: () => Get.to(() => const ChangePasswordView()),
          ),
          ListTile(
            leading: const Icon(Icons.people_outline),
            title: Text(l10n.members),
            onTap: () => Get.to(() => const MembersView()),
          ),
          const Divider(),
          ListTile(
            leading: const Icon(Icons.notifications_outlined),
            title: Text(l10n.notifications),
            onTap: () => Get.to(
              () => const NotificationView(),
              binding: BindingsBuilder(() {
                Get.put(NotificationController());
              }),
            ),
          ),
          ListTile(
            leading: const Icon(Icons.campaign_outlined),
            title: Text(l10n.announcements),
            onTap: () => Get.to(
              () => const PostView(),
              binding: BindingsBuilder(() {
                Get.put(PostController());
              }),
            ),
          ),
          ListTile(
            leading: const Icon(Icons.pan_tool_outlined),
            title: Text(l10n.holdRequests),
            onTap: () => Get.to(
              () => const HoldView(),
              binding: BindingsBuilder(() {
                Get.put(HoldController());
              }),
            ),
          ),
          ListTile(
            leading: const Icon(Icons.savings_outlined),
            title: Text(l10n.depositRequests),
            onTap: () => Get.to(
              () => const DepositView(),
              binding: BindingsBuilder(() {
                Get.put(DepositController());
              }),
            ),
          ),
          ListTile(
            leading: const Icon(Icons.description_outlined),
            title: Text(l10n.contractRequests),
            onTap: () => Get.to(
              () => const ContractRequestView(),
              binding: BindingsBuilder(() {
                Get.put(ContractRequestController());
              }),
            ),
          ),
          const Divider(),
          ListTile(
            leading: const Icon(Icons.document_scanner_outlined),
            title: Text(l10n.documentScanner),
            onTap: () => Get.to(() => const DocumentScannerView()),
          ),
          ListTile(
            leading: const Icon(Icons.calculate_outlined),
            title: Text(l10n.loanCalculator),
            onTap: () => Get.to(() => const LoanCalculatorView()),
          ),
          ListTile(
            leading: const Icon(Icons.support_agent_outlined),
            title: Text(l10n.supportCenter),
            onTap: () => Get.to(() => const SupportCenterView()),
          ),
          ListTile(
            leading: const Icon(Icons.info_outline),
            title: Text(l10n.aboutApp),
            onTap: () => Get.to(() => const AboutView()),
          ),
          const Divider(),
          ListTile(
            leading: const Icon(Icons.logout),
            title: Text(l10n.logOut),
            onTap: auth.logout,
          ),
        ],
      ),
    );
  }
}

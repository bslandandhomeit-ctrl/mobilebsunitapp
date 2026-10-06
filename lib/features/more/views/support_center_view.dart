import 'package:flutter/material.dart';

import 'package:url_launcher/url_launcher.dart';

import '../../../core/widgets/app_ui.dart';
import '../../../l10n/app_localizations.dart';

/// Contact details verified against the original app's strings.xml
/// (2026-09-11) — real support phone/email/Telegram group used by BS Land
/// & Home.
class SupportCenterView extends StatelessWidget {
  const SupportCenterView({super.key});

  static const _phone = '010866035';
  static const _email = 'developer@bslandhome.com';
  static const _telegramUrl = 'https://t.me/joinchat/DBw3shSXIHbZrkzSDRnnPA';

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    return AppPage(
      appBar: AppBar(title: Text(l10n.supportCenter)),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 28),
        children: [
          AppPanel(
            padding: EdgeInsets.zero,
            child: Column(
              children: [
                ListTile(
                  leading: const Icon(Icons.call_outlined),
                  title: const Text(_phone),
                  onTap: () => launchUrl(Uri.parse('tel:$_phone')),
                ),
                ListTile(
                  leading: const Icon(Icons.email_outlined),
                  title: const Text(_email),
                  onTap: () => launchUrl(Uri.parse('mailto:$_email')),
                ),
                ListTile(
                  leading: const Icon(Icons.telegram),
                  title: Text(l10n.telegramGroup),
                  onTap: () => launchUrl(
                    Uri.parse(_telegramUrl),
                    mode: LaunchMode.externalApplication,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

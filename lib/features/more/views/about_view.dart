import 'package:flutter/material.dart';

import 'package:package_info_plus/package_info_plus.dart';

import '../../../core/widgets/app_ui.dart';
import '../../../l10n/app_localizations.dart';

class AboutView extends StatelessWidget {
  const AboutView({super.key});

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    return AppPage(
      appBar: AppBar(title: Text(l10n.aboutApp)),
      body: Center(
        child: FutureBuilder<PackageInfo>(
          future: PackageInfo.fromPlatform(),
          builder: (context, snapshot) {
            final info = snapshot.data;
            return SingleChildScrollView(
              padding: const EdgeInsets.all(24),
              child: AppPanel(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Image.asset(
                      'assets/icon/splash_logo.png',
                      width: 112,
                      height: 56,
                      semanticLabel: 'BS',
                    ),
                    const SizedBox(height: 24),
                    const Text(
                      'BS Units',
                      style: TextStyle(
                        fontSize: 24,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 8),
                    if (info != null)
                      Text(l10n.versionLabel(info.version, info.buildNumber)),
                  ],
                ),
              ),
            );
          },
        ),
      ),
    );
  }
}

import 'package:flutter/material.dart';

import '../../l10n/app_localizations.dart';

/// Prompts for a cancel/reject reason. Returns null if the user backed out.
/// When [required] is true, the confirm button stays disabled until some
/// text is entered.
Future<String?> promptForReason(
  BuildContext context, {
  required String title,
  bool required = false,
}) {
  final controller = TextEditingController();
  return showDialog<String>(
    context: context,
    builder: (context) => StatefulBuilder(
      builder: (context, setState) {
        final l10n = AppLocalizations.of(context)!;
        return AlertDialog(
          title: Text(title),
          content: TextField(
            controller: controller,
            autofocus: true,
            maxLines: 3,
            decoration: InputDecoration(
              hintText: required ? l10n.reasonRequired : l10n.reasonOptional,
              border: const OutlineInputBorder(),
            ),
            onChanged: (_) => setState(() {}),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(context).pop(),
              child: Text(l10n.back),
            ),
            FilledButton(
              onPressed: required && controller.text.trim().isEmpty
                  ? null
                  : () => Navigator.of(context).pop(controller.text.trim()),
              child: Text(l10n.confirm),
            ),
          ],
        );
      },
    ),
  );
}

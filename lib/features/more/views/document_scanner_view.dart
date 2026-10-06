import 'dart:io';

import 'package:flutter/material.dart';

import 'package:gal/gal.dart';
import 'package:get/get.dart';
import 'package:image_cropper/image_cropper.dart';
import 'package:image_picker/image_picker.dart';

import '../../../core/widgets/app_ui.dart';
import '../../../l10n/app_localizations.dart';

/// Matches the original app's "Document Scanner" More-menu item
/// (`CropIDImageActivity` launched with `FROM_MENU=true`, verified
/// 2026-09-11): take a photo, crop it, save the crop to the device
/// gallery. Purely local — no backend endpoint is involved.
class DocumentScannerView extends StatefulWidget {
  const DocumentScannerView({super.key});

  @override
  State<DocumentScannerView> createState() => _DocumentScannerViewState();
}

class _DocumentScannerViewState extends State<DocumentScannerView> {
  final _picker = ImagePicker();
  File? _savedPreview;
  bool _isBusy = false;

  Future<void> _scan() async {
    final l10n = AppLocalizations.of(context)!;
    final photo = await _picker.pickImage(
      source: ImageSource.camera,
      imageQuality: 90,
    );
    if (photo == null) return;

    final cropped = await ImageCropper().cropImage(
      sourcePath: photo.path,
      uiSettings: [
        AndroidUiSettings(toolbarTitle: l10n.cropImage, lockAspectRatio: false),
        IOSUiSettings(title: l10n.cropImage),
      ],
    );
    if (cropped == null) return;

    setState(() => _isBusy = true);
    try {
      await Gal.putImage(cropped.path, album: 'BSUnits');
      if (!mounted) return;
      setState(() => _savedPreview = File(cropped.path));
      Get.snackbar(l10n.imageSaved, l10n.imageSavedToGallery);
    } catch (_) {
      if (mounted) Get.snackbar(l10n.error, l10n.failedToSaveImage);
    } finally {
      if (mounted) setState(() => _isBusy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    return AppPage(
      appBar: AppBar(title: Text(l10n.documentScanner)),
      body: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: AppPanel(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                if (_savedPreview != null) ...[
                  ClipRRect(
                    borderRadius: BorderRadius.circular(12),
                    child: Image.file(
                      _savedPreview!,
                      height: 220,
                      fit: BoxFit.contain,
                    ),
                  ),
                  const SizedBox(height: 24),
                ] else ...[
                  Icon(
                    Icons.document_scanner_outlined,
                    size: 72,
                    color: Theme.of(context).colorScheme.primary,
                  ),
                  const SizedBox(height: 16),
                  Text(l10n.documentScannerHint, textAlign: TextAlign.center),
                  const SizedBox(height: 24),
                ],
                ElevatedButton.icon(
                  onPressed: _isBusy ? null : _scan,
                  icon: _isBusy
                      ? const SizedBox(
                          width: 16,
                          height: 16,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.camera_alt_outlined),
                  label: Text(
                    _savedPreview == null ? l10n.takePhoto : l10n.scanAnother,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

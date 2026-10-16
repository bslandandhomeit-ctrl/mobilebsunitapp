import 'dart:io';

import 'package:flutter/material.dart';

import 'package:get/get.dart';
import 'package:image_picker/image_picker.dart';

import '../../../core/widgets/app_ui.dart';
import '../../../l10n/app_localizations.dart';
import '../../deposit/views/deposit_request_form_view.dart';
import '../../hold/views/hold_request_form_view.dart';
import '../data/unit_comment_repository.dart';
import '../data/projects_repository.dart';
import 'unit_information_card.dart';
import '../models/unit.dart';
import '../models/unit_comment.dart';

/// The unit lifecycle: AVAILABLE -> hold -> HOLD -> deposit -> DEPOSIT ->
/// (once the deposit is APPROVED) -> contract -> CONTRACT. Verified against
/// the backend source (2026-09-11): a contract request needs an approved
/// deposit request's id (`unit_deposit_request_id`), not a raw unit id, so
/// that step is started from My Requests > Deposit Requests instead of here.
class UnitDetailView extends StatefulWidget {
  const UnitDetailView({super.key, required this.unit});

  final Unit unit;

  @override
  State<UnitDetailView> createState() => _UnitDetailViewState();
}

class _UnitDetailViewState extends State<UnitDetailView> {
  late Unit _unit;
  bool _loadingUnit = true;
  String? _unitError;
  final _commentRepository = UnitCommentRepository();
  final _commentController = TextEditingController();
  final _picker = ImagePicker();
  List<UnitComment> _comments = [];
  final _pendingPhotos = <XFile>[];
  bool _isLoadingComments = true;
  bool _isPostingComment = false;

  @override
  void initState() {
    super.initState();
    _unit = widget.unit;
    _loadUnit();
    _loadComments();
  }

  Future<void> _loadUnit() async {
    try {
      final detail = await ProjectsRepository().getUnit(widget.unit.id);
      if (mounted) setState(() => _unit = detail);
    } catch (_) {
      if (mounted) {
        setState(
          () => _unitError = AppLocalizations.of(context)!.detailLoadError,
        );
      }
    } finally {
      if (mounted) setState(() => _loadingUnit = false);
    }
  }

  Future<void> _loadComments() async {
    try {
      _comments = (await _commentRepository.getComments(widget.unit.id)).items;
    } catch (_) {
      // Non-fatal — just leave the list empty.
    } finally {
      if (mounted) setState(() => _isLoadingComments = false);
    }
  }

  Future<void> _addPhotos() async {
    final picked = await _picker.pickMultiImage(imageQuality: 85);
    if (picked.isNotEmpty) setState(() => _pendingPhotos.addAll(picked));
  }

  void _removePendingPhoto(int index) {
    setState(() => _pendingPhotos.removeAt(index));
  }

  Future<void> _postComment() async {
    final content = _commentController.text.trim();
    if (content.isEmpty) return;
    setState(() => _isPostingComment = true);
    try {
      await _commentRepository.addComment(
        widget.unit.id,
        content,
        mediaPaths: _pendingPhotos.map((f) => f.path).toList(),
      );
      _commentController.clear();
      _pendingPhotos.clear();
      await _loadComments();
    } catch (_) {
      if (mounted) {
        final l10n = AppLocalizations.of(context)!;
        Get.snackbar(l10n.error, l10n.failedToPostComment);
      }
    } finally {
      if (mounted) setState(() => _isPostingComment = false);
    }
  }

  @override
  void dispose() {
    _commentController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context)!;
    final available = _unit.status?.toUpperCase() == 'AVAILABLE';
    final held = _unit.status?.toUpperCase() == 'HOLD';
    return AppPage(
      appBar: AppBar(
        title: Text(_unit.code),
        actions: [
          Padding(
            padding: const EdgeInsets.only(right: 16),
            child: Center(
              child: AppStatusBadge(_unit.status ?? l.unknownStatus),
            ),
          ),
        ],
      ),
      bottomNavigationBar: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 12),
          child: Row(
            children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: _loadingUnit || !available
                      ? null
                      : () => _request(false),
                  child: Text(l.holdButtonLabel),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: FilledButton(
                  onPressed: _loadingUnit || !(available || held)
                      ? null
                      : () => _request(true),
                  child: Text(l.depositButtonLabel),
                ),
              ),
            ],
          ),
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
        children: [
          if (_loadingUnit) const LinearProgressIndicator(),
          if (_unitError != null)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(_unitError!),
                  TextButton(
                    onPressed: () {
                      setState(() {
                        _loadingUnit = true;
                        _unitError = null;
                      });
                      _loadUnit();
                    },
                    child: Text(l.retry),
                  ),
                ],
              ),
            ),
          AppSectionTitle(l.previewLabel),
          GestureDetector(
            onTap: _unit.previewUrl == null
                ? null
                : () => _openImage(_unit.previewUrl!),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(24),
              child: AppImage(url: _unit.previewUrl, height: 230),
            ),
          ),
          const SizedBox(height: 24),
          UnitInformationCard(unit: _unit, showFeatures: true),
          const SizedBox(height: 24),
          _gallery(l.exteriorLabel, _unit.exteriorUrls),
          _gallery(l.interiorLabel, _unit.interiorUrls),
          _gallery(l.floorPlanLabel, _unit.floorPlanUrls),
          AppPanel(
            child: ExpansionTile(
              tilePadding: EdgeInsets.zero,
              title: Text(l.comments),
              children: [
                TextField(
                  controller: _commentController,
                  decoration: InputDecoration(hintText: l.addComment),
                  minLines: 2,
                  maxLines: 5,
                ),
                Row(
                  mainAxisAlignment: MainAxisAlignment.end,
                  children: [
                    IconButton(
                      onPressed: _addPhotos,
                      tooltip: l.attachPhotos,
                      icon: const Icon(Icons.add_photo_alternate_outlined),
                    ),
                    IconButton(
                      onPressed: _isPostingComment ? null : _postComment,
                      tooltip: l.addComment,
                      icon: _isPostingComment
                          ? const SizedBox(
                              width: 20,
                              height: 20,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Icon(Icons.send),
                    ),
                  ],
                ),
                Wrap(
                  spacing: 8,
                  children: [
                    for (var i = 0; i < _pendingPhotos.length; i++)
                      Stack(
                        children: [
                          Image.file(
                            File(_pendingPhotos[i].path),
                            width: 72,
                            height: 72,
                            fit: BoxFit.cover,
                          ),
                          Positioned(
                            right: 0,
                            top: 0,
                            child: IconButton(
                              onPressed: () => _removePendingPhoto(i),
                              icon: const Icon(Icons.cancel),
                            ),
                          ),
                        ],
                      ),
                  ],
                ),
                if (_isLoadingComments)
                  const CircularProgressIndicator()
                else if (_comments.isEmpty)
                  Padding(
                    padding: const EdgeInsets.all(16),
                    child: Text(l.noCommentsYet),
                  )
                else
                  for (final c in _comments)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          Text(c.content),
                          for (final url in c.mediaUrls)
                            AppImage(url: url, height: 160),
                        ],
                      ),
                    ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _gallery(String title, List<String> images) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      AppSectionTitle(title, count: images.isEmpty ? null : images.length),
      if (images.isEmpty)
        AppPanel(
          child: Text(
            AppLocalizations.of(context)!.noImagesLabel,
            style: Theme.of(context).textTheme.bodyMedium,
          ),
        )
      else
        for (final url in images)
          Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: GestureDetector(
              onTap: () => _openImage(url),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(20),
                child: AppImage(
                  url: url,
                  height: 220,
                  fit: title == AppLocalizations.of(context)!.floorPlanLabel
                      ? BoxFit.contain
                      : BoxFit.cover,
                ),
              ),
            ),
          ),
      const SizedBox(height: 24),
    ],
  );
  void _openImage(String url) => Navigator.of(context).push(
    MaterialPageRoute(
      builder: (context) => Scaffold(
        backgroundColor: Colors.black,
        appBar: AppBar(
          backgroundColor: Colors.black,
          foregroundColor: Colors.white,
        ),
        body: Center(
          child: InteractiveViewer(
            minScale: .5,
            maxScale: 4,
            child: AppImage(
              url: url,
              fit: BoxFit.contain,
              height: MediaQuery.sizeOf(context).height * .75,
            ),
          ),
        ),
      ),
    ),
  );
  Future<void> _request(bool deposit) async {
    final result = await Get.to(
      () => deposit
          ? DepositRequestFormView(unit: _unit)
          : HoldRequestFormView(unit: _unit),
    );
    if (result == true && mounted) {
      setState(() => _loadingUnit = true);
      await _loadUnit();
    }
  }
}

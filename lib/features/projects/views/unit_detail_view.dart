import 'dart:io';

import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:image_picker/image_picker.dart';

import '../../../l10n/app_localizations.dart';
import '../../deposit/views/deposit_request_form_view.dart';
import '../../hold/views/hold_request_form_view.dart';
import '../data/unit_comment_repository.dart';
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
    _loadComments();
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
    final l10n = AppLocalizations.of(context)!;
    return Scaffold(
      appBar: AppBar(title: Text(widget.unit.name)),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(l10n.unitStatus, style: Theme.of(context).textTheme.labelMedium),
          Chip(label: Text(widget.unit.status ?? 'UNKNOWN')),
          const SizedBox(height: 24),
          ..._actionsFor(context, widget.unit.status?.toUpperCase()),
          const Divider(height: 40),
          Text(l10n.comments, style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 12),
          Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Expanded(
                child: TextField(
                  controller: _commentController,
                  decoration: InputDecoration(
                    hintText: l10n.addComment,
                    border: const OutlineInputBorder(),
                    isDense: true,
                  ),
                ),
              ),
              IconButton(
                icon: const Icon(Icons.add_photo_alternate_outlined),
                tooltip: l10n.attachPhotos,
                onPressed: _addPhotos,
              ),
              IconButton(
                icon: _isPostingComment
                    ? const SizedBox(
                        width: 16,
                        height: 16,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.send),
                onPressed: _isPostingComment ? null : _postComment,
              ),
            ],
          ),
          if (_pendingPhotos.isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  for (var i = 0; i < _pendingPhotos.length; i++)
                    Stack(
                      children: [
                        ClipRRect(
                          borderRadius: BorderRadius.circular(8),
                          child: Image.file(
                            File(_pendingPhotos[i].path),
                            width: 64,
                            height: 64,
                            fit: BoxFit.cover,
                          ),
                        ),
                        Positioned(
                          top: -8,
                          right: -8,
                          child: IconButton(
                            icon: const Icon(Icons.cancel, size: 18),
                            onPressed: () => _removePendingPhoto(i),
                          ),
                        ),
                      ],
                    ),
                ],
              ),
            ),
          const SizedBox(height: 12),
          if (_isLoadingComments)
            const Center(child: CircularProgressIndicator())
          else if (_comments.isEmpty)
            Text(l10n.noCommentsYet)
          else
            ..._comments.map(
              (c) => Padding(
                padding: const EdgeInsets.symmetric(vertical: 6),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(c.content),
                    if (c.mediaUrls.isNotEmpty)
                      Padding(
                        padding: const EdgeInsets.only(top: 6),
                        child: Wrap(
                          spacing: 8,
                          runSpacing: 8,
                          children: c.mediaUrls
                              .map(
                                (url) => ClipRRect(
                                  borderRadius: BorderRadius.circular(8),
                                  child: CachedNetworkImage(
                                    imageUrl: url,
                                    width: 64,
                                    height: 64,
                                    fit: BoxFit.cover,
                                    errorWidget: (_, _, _) => const SizedBox(width: 64, height: 64),
                                  ),
                                ),
                              )
                              .toList(),
                        ),
                      ),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }

  List<Widget> _actionsFor(BuildContext context, String? status) {
    final l10n = AppLocalizations.of(context)!;
    switch (status) {
      case 'AVAILABLE':
        // Verified against the backend (`Unit::allowDeposit()`,
        // 2026-09-12): a deposit can be requested directly from AVAILABLE,
        // not only after a HOLD. Both actions are shown unconditionally
        // here (matching the original Android app's UnitDetailActivity,
        // which shows both buttons regardless of status and lets the
        // backend reject what doesn't apply) since the hold-creation
        // endpoint itself is permanently disabled server-side (`Api/
        // UnitHoldController@hold` — dead code behind an unconditional
        // `return` since a 2020-07-21 change) and correctly surfaces
        // "This functionality is currently disable." if tapped.
        return [
          ElevatedButton(
            onPressed: () => Get.to(() => HoldRequestFormView(unit: widget.unit)),
            child: Text(l10n.requestHold),
          ),
          const SizedBox(height: 12),
          ElevatedButton(
            onPressed: () => Get.to(() => DepositRequestFormView(unit: widget.unit)),
            child: Text(l10n.requestDeposit),
          ),
        ];
      case 'HOLD':
        return [
          ElevatedButton(
            onPressed: () => Get.to(() => DepositRequestFormView(unit: widget.unit)),
            child: Text(l10n.convertToDeposit),
          ),
        ];
      case 'DEPOSIT':
        return [
          Text(l10n.depositInProgressHint),
        ];
      default:
        return [
          Text(l10n.noActionsAvailable),
        ];
    }
  }
}

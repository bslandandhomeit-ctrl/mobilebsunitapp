import 'package:flutter/material.dart';

import 'package:flutter_widget_from_html_core/flutter_widget_from_html_core.dart';

import '../../../core/widgets/app_ui.dart';
import '../../../l10n/app_localizations.dart';
import '../data/post_repository.dart';
import '../models/post.dart';

class PostDetailView extends StatefulWidget {
  const PostDetailView({super.key, required this.postId});

  final int postId;

  @override
  State<PostDetailView> createState() => _PostDetailViewState();
}

class _PostDetailViewState extends State<PostDetailView> {
  final _repository = PostRepository();
  Post? _post;
  bool _isLoading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      _post = await _repository.getPost(widget.postId);
    } catch (_) {
      if (mounted) _error = AppLocalizations.of(context)!.failedToLoadPost;
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AppPage(
      appBar: AppBar(
        title: Text(_post?.title ?? AppLocalizations.of(context)!.post),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
          ? AppEmptyState(message: _error!)
          : ListView(
              children: [
                if (_post!.featuredImageUrl != null)
                  AppImage(url: _post!.featuredImageUrl!, height: 200),
                Padding(
                  padding: const EdgeInsets.fromLTRB(20, 12, 20, 32),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        _post!.title ?? '',
                        style: Theme.of(context).textTheme.headlineSmall,
                      ),
                      const SizedBox(height: 16),
                      HtmlWidget(_post!.content ?? ''),
                    ],
                  ),
                ),
              ],
            ),
    );
  }
}

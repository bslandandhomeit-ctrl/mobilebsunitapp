import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_widget_from_html_core/flutter_widget_from_html_core.dart';

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
    return Scaffold(
      appBar: AppBar(title: Text(_post?.title ?? AppLocalizations.of(context)!.post)),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error!))
              : ListView(
                  children: [
                    if (_post!.featuredImageUrl != null)
                      CachedNetworkImage(
                        imageUrl: _post!.featuredImageUrl!,
                        width: double.infinity,
                        height: 200,
                        fit: BoxFit.cover,
                        errorWidget: (_, _, _) => const SizedBox(height: 200),
                      ),
                    Padding(
                      padding: const EdgeInsets.all(16),
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

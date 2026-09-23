import 'package:flutter/material.dart';

import '../../../core/network/api_exception.dart';
import '../../../l10n/app_localizations.dart';
import '../data/auth_repository.dart';
import '../models/user.dart';

class MembersView extends StatefulWidget {
  const MembersView({super.key});

  @override
  State<MembersView> createState() => _MembersViewState();
}

class _MembersViewState extends State<MembersView> {
  final _repository = AuthRepository();
  bool _isLoading = true;
  String? _error;
  List<AppUser> _members = [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });
    try {
      _members = (await _repository.getMembers()).items;
    } on ApiException catch (e) {
      _error = e.message;
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    return Scaffold(
      appBar: AppBar(title: Text(l10n.members)),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error!))
              : _members.isEmpty
                  ? Center(child: Text(l10n.noMembersFound))
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: ListView.separated(
                        itemCount: _members.length,
                        separatorBuilder: (_, _) => const Divider(height: 1),
                        itemBuilder: (context, index) {
                          final m = _members[index];
                          return ListTile(
                            leading: CircleAvatar(
                              backgroundImage: m.avatarUrl != null ? NetworkImage(m.avatarUrl!) : null,
                              child: m.avatarUrl == null ? const Icon(Icons.person) : null,
                            ),
                            title: Text(m.name),
                            subtitle: Text(m.phoneNumber ?? ''),
                          );
                        },
                      ),
                    ),
    );
  }
}

import 'package:flutter/foundation.dart';

import '../constants/api_constants.dart';
import '../models/app_version.dart';
import 'api_client.dart';

/// Verified: `/api/app_version/{platform}/latestBuild` requires auth
/// (behind `auth:api` middleware in the backend's route group), so this
/// can only be checked after login — not on the splash screen.
class AppVersionRepository {
  final ApiClient _client = ApiClient.instance;

  Future<AppVersion?> getLatestBuild() async {
    final platform = defaultTargetPlatform == TargetPlatform.iOS ? 'ios' : 'android';
    try {
      final response = await _client.get<Map<String, dynamic>>(
        '${ApiConstants.appVersionPath}/$platform/latestBuild',
      );
      final data = response.data;
      if (data == null) return null;
      return AppVersion.fromJson(data['data'] is Map<String, dynamic> ? data['data'] : data);
    } catch (_) {
      // Non-critical — don't block the app if this check fails.
      return null;
    }
  }
}

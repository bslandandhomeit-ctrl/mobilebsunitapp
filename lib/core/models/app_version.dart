// Verified against the backend source (app/AppVersion.php,
// Api/AppVersionController) — 2026-09-11.
class AppVersion {
  AppVersion({
    required this.build,
    required this.version,
    this.description,
    this.forcedUpdate = false,
  });

  factory AppVersion.fromJson(Map<String, dynamic> json) => AppVersion(
    build: (json['build'] as num?)?.toInt() ?? 0,
    version: (json['version'] ?? '').toString(),
    description: json['description']?.toString(),
    forcedUpdate: json['forced_update'] == true || json['forced_update'] == 1,
  );

  final int build;
  final String version;
  final String? description;
  final bool forcedUpdate;
}

import 'package:flutter/material.dart';

class AppTheme {
  AppTheme._();

  // Placeholder brand color — swap for BS Units' real brand palette
  // once available (check banner/ assets in the original Android project).
  static const _seed = Color(0xFF1565C0);

  static ThemeData get light => ThemeData(
    useMaterial3: true,
    colorScheme: ColorScheme.fromSeed(seedColor: _seed),
    inputDecorationTheme: const InputDecorationTheme(
      border: OutlineInputBorder(),
    ),
  );
}

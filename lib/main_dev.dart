// iOS: flutter run -t lib/main_dev.dart
// Android: flutter run --flavor dev -t lib/main_dev.dart
import 'core/constants/api_constants.dart';
import 'main.dart' as app;

Future<void> main() async {
  ApiConstants.useDevelopment();
  await app.startBsUnits();
}

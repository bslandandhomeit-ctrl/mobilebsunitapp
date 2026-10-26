// Production flavor entry point: talks to the real live server.
// iOS: flutter run -t lib/main_prod.dart
// Android: flutter run --flavor prod -t lib/main_prod.dart
import 'core/constants/api_constants.dart';
import 'main.dart' as app;

Future<void> main() async {
  ApiConstants.useProduction();
  await app.startBsUnits();
}

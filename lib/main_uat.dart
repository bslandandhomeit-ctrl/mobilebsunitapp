// UAT flavor entry point: talks to the UAT server (10.3.0.99 — needs the VPN).
// Run with:  flutter run --flavor uat -t lib/main_uat.dart
import 'core/constants/api_constants.dart';
import 'main.dart' as app;

Future<void> main() async {
  ApiConstants.useUat();
  await app.startBsUnits();
}

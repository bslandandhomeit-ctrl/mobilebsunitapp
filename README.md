# bs_contract_flutter

BS Units - Flutter recreation of bs-contract-android

Local backend development: see [RUN.md](RUN.md#local-development).
Use `lib/main_dev.dart` (Android flavor `dev`) for `http://127.0.0.1:8000/`.
Android over USB requires `adb reverse tcp:8000 tcp:8000` first.

## Getting Started

This project is a starting point for a Flutter application.

A few resources to get you started if this is your first Flutter project:

- [Learn Flutter](https://docs.flutter.dev/get-started/learn-flutter)
- [Write your first Flutter app](https://docs.flutter.dev/get-started/codelab)
- [Flutter learning resources](https://docs.flutter.dev/reference/learning-resources)

For help getting started with Flutter development, view the
[online documentation](https://docs.flutter.dev/), which offers tutorials,
samples, guidance on mobile development, and a full API reference.

cd /Users/ramonoem/Documents/project/mobilebsunitapp

/Users/ramonoem/Library/Android/sdk/platform-tools/adb -s R5CY61BJLCR reverse tcp:8000 tcp:8000

flutter run -d R5CY61BJLCR --dart-define-from-file=../webbsunitapp/.docker/local/mobile-local.json

<!-- flutter run -d 6E902F9A-273E-4A07-B821-A15C94849980 -t lib/main_uat.dart -->

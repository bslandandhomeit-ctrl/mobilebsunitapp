# bs_contract_flutter

BS Units - Flutter recreation of bs-contract-android

Local backend development: see [RUN.md](RUN.md#local-development).
Use `lib/main_dev.dart` (Android flavor `dev`) for `http://127.0.0.1:8000/`.
Android over USB requires `adb reverse tcp:8000 tcp:8000` first.

## Release an Android APK

The app version and build number come from `pubspec.yaml`:

```yaml
version: 1.0.1+25
```

The example above means version `1.0.1`, build `25`.

### UAT APK

Build the UAT app from the project directory. UAT uses the UAT server and the
Android package `com.bslandandhome.bsunits.dev`:

```bash
sh tools/release/build-release.sh --dev --allow-debug-key
```

The script runs the tests, builds the APK, and places the result in `release/`.
To put a copy on the Desktop with the standard UAT name, run:

```bash
cp release/BSUnits-1.0.1-25-dev-DEBUGKEY.apk ~/Desktop/BSUnits-UAT-1.0.1-25.apk
```

Update the version and build numbers in that command when `pubspec.yaml`
changes. To install it on a connected Android phone:

```bash
adb install -r ~/Desktop/BSUnits-UAT-1.0.1-25.apk
```

Use `adb uninstall com.bslandandhome.bsunits.dev` first only when a clean
installation is required; uninstalling clears the app's saved data and login.

### Production APK

Production must use the release signing key configured in
`android/key.properties`:

```bash
sh tools/release/build-release.sh --prod
```

Do not use `--allow-debug-key` for an APK distributed to production users or
uploaded to Google Play.

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

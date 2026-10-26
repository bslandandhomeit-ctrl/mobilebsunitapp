# How to run BS Units — Development, UAT and Production

## Local development

Development image URLs use `http://10.3.0.99:8082/` (UAT), while API requests
stay on the local backend. Keep the phone connected to a network that can reach
UAT. Override the image server with `--dart-define=IMAGE_BASE_URL=http://HOST:PORT/`.

Use `lib/main_dev.dart` to connect to your local PHP service at
`http://127.0.0.1:8000/`. The PHP service's database may still be on `10.3.0.99`;
the mobile API URL points to the PHP service, not to its database.

Start the BS Units PHP backend from its own directory:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

iOS simulator:

```bash
flutter run -t lib/main_dev.dart
```

Android phone/emulator connected through ADB (run the reverse command again
after reconnecting the device; add `adb -s DEVICE_ID` if several are connected):

```bash
adb reverse tcp:8000 tcp:8000
flutter run --flavor dev -t lib/main_dev.dart
```

Alternatively, an Android emulator can use the host alias without ADB reverse:

```bash
flutter run --flavor dev -t lib/main_dev.dart --dart-define=API_BASE_URL=http://10.0.2.2:8000/
```

For a physical phone over Wi-Fi, start PHP with `--host=0.0.0.0` and replace
`YOUR_MAC_LAN_IP` with your Mac's IP on the same network:

```bash
flutter run --flavor dev -t lib/main_dev.dart --dart-define=API_BASE_URL=http://YOUR_MAC_LAN_IP:8000/
```

For a physical iPhone use the same URL override but omit `--flavor dev`.
The app shows a DEVELOPMENT banner. Android dev reuses UAT's registered Firebase
package (`com.bslandandhome.bsunits.dev`), so installing dev replaces UAT.
Production remains a separate Android installation. Restart when switching environments.

VS Code: select **BS Units — Android Development (local backend)** or
**BS Units — iOS Development (local backend)**. Android localhost needs ADB reverse.

The app has **three Android flavors** and matching Dart entry points:

| Flavor | Server | Android package | When to use |
|---|---|---|---|
| **dev** | `http://127.0.0.1:8000/` (configurable) | `com.bslandandhome.bsunits.dev` | local PHP backend |
| **uat** | `http://10.3.0.99:8082/` (UAT) | `com.bslandandhome.bsunits.dev` | testing — needs the **FortiClient VPN** on |
| **prod** | `https://contract.chaktomukcity.com/` (live) | `com.bslandandhome.bsunits` | real users / release |

Production and UAT install **side by side**; dev replaces UAT because they share a package.
The UAT app shows a blue **"UAT"** banner in the corner; production has none.

> ⚠️ Do **not** run `flutter run` with no flavor — that defaults to **production**.
> Select the environment entry point. Android also requires its matching flavor, as below.

## Run on Android (development)

**UAT** (turn on the VPN first):
```bash
flutter run --flavor uat -t lib/main_uat.dart
```

**Production:**
```bash
flutter run --flavor prod -t lib/main_prod.dart
```

## Run on iOS (development)

Xcode has no custom flavor schemes. Select the backend using the Dart entry point:

```bash
flutter run -t lib/main_uat.dart    # UAT — turn on the VPN first
flutter run -t lib/main_prod.dart   # Production
```

In VS Code, select **BS Units — iOS UAT (debug)** for the iPhone simulator,
or the matching **Android** configuration for Android. Press **F5**.
The iOS configurations omit `--flavor`; Android configurations keep it.
Stop the app and start it again when switching environments.

## Build an installable APK

Use the helper script (handles signing, version, checks):

**UAT test APK** (no release key needed):
```bash
sh tools/release/build-release.sh --dev --allow-debug-key
```

**Production APK** (signed with the real release key in `android/key.properties`):
```bash
sh tools/release/build-release.sh --prod
```

Output lands in `release/BSUnits-<version>-<build>-<prod|dev>.apk`.

Or build directly with Flutter:
```bash
flutter build apk --release --flavor uat  -t lib/main_uat.dart     # UAT
flutter build apk --release --flavor prod -t lib/main_prod.dart    # Production
```

## Notes

- **Which server am I on?** The UAT app shows the blue "UAT" corner banner; production shows nothing.
- **UAT needs the VPN** (FortiClient) to reach `10.3.0.99`. Without it, the app can't load data.
- **Firebase / push notifications** run only in the production flavor (by design); UAT skips them.
- **iOS:** run with the entry point, e.g. `flutter run -t lib/main_uat.dart`. Separate iOS bundle
  IDs per flavor would need Xcode schemes (not set up yet) — the Dart side already picks the right server.
- A one-off custom server is still possible:
  `flutter run -t lib/main_uat.dart --dart-define=API_BASE_URL=http://10.0.2.2:8000/`
  (`10.0.2.2` is the Android emulator's alias for your computer's localhost.)

## Android: build succeeded but Flutter cannot find the APK

Select **BS Units — Android UAT (debug)** in VS Code when using an Android
phone or emulator. The iOS configurations omit Android's required flavor.
Running an iOS configuration on Android builds flavored APKs, but Flutter looks
for an unflavored APK and reports that it cannot find the file.

For UAT, use:

```bash
flutter run --flavor uat -t lib/main_uat.dart
```

For production, use `--flavor prod -t lib/main_prod.dart`.

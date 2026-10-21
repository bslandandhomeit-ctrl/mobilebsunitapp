# How to run BS Units — UAT vs Production

The app has **two flavors** so you never mix up the server:

| Flavor | Server | Android package | When to use |
|---|---|---|---|
| **uat** | `http://10.3.0.99:8082/` (UAT) | `com.bslandandhome.bsunits.dev` | testing — needs the **FortiClient VPN** on |
| **prod** | `https://contract.chaktomukcity.com/` (live) | `com.bslandandhome.bsunits` | real users / release |

The two install **side by side** (different package), so you can keep both on one phone.
The UAT app shows a blue **"UAT"** banner in the corner; production has none.

> ⚠️ Do **not** run `flutter run` with no flavor — that defaults to **production**.
> Always pass a flavor and its entry point, as below.

## Run on a connected phone / emulator (development)

**UAT** (turn on the VPN first):
```bash
flutter run --flavor uat -t lib/main_uat.dart
```

**Production:**
```bash
flutter run --flavor prod -t lib/main_prod.dart
```

In VS Code / Android Studio, pick the matching run configuration, or add the same
`--flavor … -t …` under "args".

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

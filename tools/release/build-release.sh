#!/bin/sh
# Build a release APK of BS Units, ready to send to users or upload yourself.
#
#   sh tools/release/build-release.sh                 # production app, production server
#   sh tools/release/build-release.sh --bump          # same, and raise the build number first
#   sh tools/release/build-release.sh --aab           # also build the .aab for Google Play
#   sh tools/release/build-release.sh --dev           # the ".dev" package (installs next to the real app)
#
# Other options:
#   --define-file FILE   build against another server (a --dart-define-from-file JSON, e.g. UAT)
#                        UAT: --dev --allow-debug-key --define-file tools/release/uat.json
#   --skip-tests         do not run `flutter test` first
#   --allow-debug-key    build even though the release keystore is missing (TEST ONLY: such an
#                        APK cannot update the app users already have and cannot go on Play)
#
# Result: release/BSUnits-<version>-<build>-<prod|dev>.apk (+ .sha256, + .aab with --aab)
#
# Signing: put the release keystore details in android/key.properties (git-ignored):
#   storeFile=/absolute/path/to/bs-unit-release-key.keystore
#   storePassword=...
#   keyAlias=...
#   keyPassword=...
#   storeType=jks        (or pkcs12)
set -eu
cd "$(dirname "$0")/../.."

FLAVOR=prod; BUMP=0; AAB=0; TESTS=1; ALLOW_DEBUG=0; DEFINE_FILE=""
while [ "$#" -gt 0 ]; do
  case "$1" in
    --prod) FLAVOR=prod ;;
    --dev) FLAVOR=dev ;;
    --bump) BUMP=1 ;;
    --aab) AAB=1 ;;
    --skip-tests) TESTS=0 ;;
    --allow-debug-key) ALLOW_DEBUG=1 ;;
    --define-file) shift; DEFINE_FILE="${1:?--define-file needs a path}" ;;
    -h|--help) sed -n '2,25p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) echo "Unknown option: $1 (try --help)" >&2; exit 2 ;;
  esac
  shift
done

command -v flutter >/dev/null 2>&1 || { echo "flutter is not on PATH" >&2; exit 1; }

if [ "$FLAVOR" = prod ]; then APP_ID=com.bslandandhome.bsunits; else APP_ID=com.bslandandhome.bsunits.dev; fi

# --- signing key ---------------------------------------------------------
if [ -f android/key.properties ]; then
  STORE=$(sed -n 's/^storeFile=//p' android/key.properties | head -1)
  # a relative storeFile is resolved from android/app, like Gradle does
  case "$STORE" in /*) STORE_PATH="$STORE" ;; *) STORE_PATH="android/app/$STORE" ;; esac
  [ -f "$STORE_PATH" ] || { echo "android/key.properties points at a keystore that does not exist: $STORE_PATH" >&2; exit 1; }
  SIGNED_WITH="release key"
elif [ "$ALLOW_DEBUG" = 1 ]; then
  SIGNED_WITH="DEBUG KEY (test only)"
  echo "WARNING: android/key.properties is missing - this build is signed with the debug key."
else
  echo "android/key.properties is missing, so the APK would be signed with the debug key." >&2
  echo "It could not update the app users already have, nor be uploaded to Google Play." >&2
  echo "Add the release keystore (see the top of this script), or pass --allow-debug-key for a test build." >&2
  exit 1
fi

# --- version -------------------------------------------------------------
VERSION_LINE=$(sed -n 's/^version: *//p' pubspec.yaml | head -1)
NAME=${VERSION_LINE%%+*}; BUILD=${VERSION_LINE##*+}
if [ "$BUMP" = 1 ]; then
  BUILD=$((BUILD + 1))
  sed -i.bak "s/^version: .*/version: $NAME+$BUILD/" pubspec.yaml && rm -f pubspec.yaml.bak
  echo "Build number raised: $NAME+$BUILD (pubspec.yaml changed - commit it)"
fi

DEFINES=""
SERVER="production (https://contract.chaktomukcity.com/)"
if [ -n "$DEFINE_FILE" ]; then
  [ -f "$DEFINE_FILE" ] || { echo "No such file: $DEFINE_FILE" >&2; exit 1; }
  DEFINES="--dart-define-from-file=$DEFINE_FILE"
  SERVER="from $DEFINE_FILE"
fi

echo "==> BS Units $NAME ($BUILD) | package $APP_ID | server: $SERVER | signed with: $SIGNED_WITH"

flutter pub get
if [ "$TESTS" = 1 ]; then
  echo "==> Tests"
  flutter test
fi

OUT=release
mkdir -p "$OUT"
BASE="BSUnits-$NAME-$BUILD-$FLAVOR"
[ "$SIGNED_WITH" = "release key" ] || BASE="$BASE-DEBUGKEY"

echo "==> Building APK"
# shellcheck disable=SC2086
ORG_GRADLE_PROJECT_bsAppId="$APP_ID" flutter build apk --release $DEFINES
cp build/app/outputs/flutter-apk/app-release.apk "$OUT/$BASE.apk"
( cd "$OUT" && shasum -a 256 "$BASE.apk" > "$BASE.apk.sha256" )

if [ "$AAB" = 1 ]; then
  echo "==> Building App Bundle"
  # shellcheck disable=SC2086
  ORG_GRADLE_PROJECT_bsAppId="$APP_ID" flutter build appbundle --release $DEFINES
  cp build/app/outputs/bundle/release/app-release.aab "$OUT/$BASE.aab"
fi

# --- show who signed it (needs Java; Android Studio's is used if none is on PATH) ---
SDK="${ANDROID_HOME:-$HOME/Library/Android/sdk}"
APKSIGNER=$(ls "$SDK"/build-tools/*/apksigner 2>/dev/null | sort | tail -1 || true)
if ! command -v java >/dev/null 2>&1 || ! java -version >/dev/null 2>&1; then
  for J in "/Applications/Android Studio.app/Contents/jbr/Contents/Home" "/Applications/Android Studio.app/Contents/jre/Contents/Home"; do
    [ -x "$J/bin/java" ] && { JAVA_HOME="$J"; PATH="$J/bin:$PATH"; export JAVA_HOME PATH; break; }
  done
fi
if [ -n "$APKSIGNER" ] && java -version >/dev/null 2>&1; then
  echo "==> Signature"
  "$APKSIGNER" verify --print-certs "$OUT/$BASE.apk" | grep -E "certificate DN|SHA-256 digest" | head -2
fi

echo
echo "DONE"
ls -lh "$OUT/$BASE".* | awk '{print "  " $5 "  " $9}'
echo "  package: $APP_ID   version: $NAME ($BUILD)   signed with: $SIGNED_WITH"

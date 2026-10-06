#!/bin/sh
set -eu
cd "$(dirname "$0")/../.."
ADB="${ANDROID_HOME:-$HOME/Library/Android/sdk}/platform-tools/adb"
if [ "$#" -gt 0 ]; then
  "$ADB" -s "$1" reverse tcp:8000 tcp:8000
  flutter run -d "$1" --dart-define-from-file=../webbsunitapp/.docker/local/mobile-local.json
else
  "$ADB" reverse tcp:8000 tcp:8000
  flutter run --dart-define-from-file=../webbsunitapp/.docker/local/mobile-local.json
fi

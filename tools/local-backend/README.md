# Connect the mobile app to the original backend

The active server runs `/Users/ramonoem/Documents/project/webbsunitapp` directly in Docker. Its database is the restored local `bsunits_backup_20261005`; the original SQL backup is unchanged. The old copy in `.local-backend/web` is inactive.

Start the backend from its project folder:

```sh
sh .docker/local/start.sh
```

Then, from this mobile project:

```sh
sh tools/local-backend/run-phone.sh R5CY61BJLCR
```

This loads `../webbsunitapp/.docker/local/mobile-local.json`, which sets `API_BASE_URL` to `http://127.0.0.1:8000/`. ADB reverse connects the phone to the Mac over USB. Keep Docker running and USB connected. A LOCAL TEST banner identifies this build. Plain `flutter run` uses the production default instead.

Local test account: phone `0990000001`, password `LocalTest123!`.

DBeaver: MariaDB, host `127.0.0.1`, port `3307`, database `bsunits_backup_20261005`, user `root`, password `local-only-bsunits`.

Backend source changes belong in the original webbsunitapp folder. See its `.docker/local/README.md` for server details. This directory contains only connection/startup helpers for the mobile workflow.

# Beloan local database

Separate local MySQL 5.7.44 database restored from `land_home_v2.zip`.
The dump header identifies `land_home_v2`, MySQL 5.7.44, source label
`BeLoan-pre-production`, and export date 26 September 2026.

Start or stop without deleting restored data:

```bash
docker compose -f tools/local-beloan/compose.yaml up -d
docker compose -f tools/local-beloan/compose.yaml stop
```

DBeaver connection:

- Driver: MySQL
- Host: `127.0.0.1`
- Port: `3308`
- Database: `land_home_v2`
- Username: `root`
- Password: `local-only-beloan`

Data persists in Docker volume `beloan-local_beloan-data`.
The MySQL port is bound to localhost; scheduled database events are disabled.
This container is independent of the BS Units database on port 3307.
No Beloan application or background worker is started by this configuration.

After the Beloan code is available, an app container on this Compose network
can connect to host `db` on port `3306`. An app running directly on the Mac
uses `127.0.0.1:3308`. Replace copied server connection settings before
starting the local application.

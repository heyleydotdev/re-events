# Upgrading

## Plugin updates

Update ReEvents from **Dashboard → Updates** like any plugin. Release builds contain production dependencies only.

## Database

Tables are created and upgraded automatically with `dbDelta` on the next admin request after an update. The schema version is stored in `reevents_db_version`.

Back up the database before a major update. Custom tables (`reevents_occurrences`, `reevents_tickets`, `reevents_bookings`, `reevents_attendees`, `reevents_payments`) are not part of a WordPress export.

## Compatibility

- PHP 8.0 or newer and WordPress 6.5 or newer are required.
- The public PHP API and REST API keep their versions independent of the plugin version.
- Breaking API changes wait for a major API version; deprecated functions stay for one major version.

## Pre-release builds

Builds before `1.0.0` are development snapshots. Their data is not migrated; start from a fresh database when moving from one snapshot to a stable release.

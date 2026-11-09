# ADR 0001: Custom tables for occurrences, tickets, bookings, and attendees

- Status: Accepted
- Date: 2026-11-09

## Context

Events, venues, and organizers fit the WordPress post model. Occurrences, tickets, bookings, and attendees do not.

They need:

- indexed date-range queries across recurrences
- atomic capacity checks
- multi-row writes inside a transaction
- stable numeric identifiers for joins and exports
- query budgets that do not depend on post meta joins

Storing them as post meta would require wide joins, serialized values, and application-level locking.

## Decision

Store occurrences, tickets, bookings, and attendees in dedicated tables created with `dbDelta`:

- `reevents_occurrences`
- `reevents_tickets`
- `reevents_bookings`
- `reevents_attendees`

Repositories in `src/data` are the only code that reads or writes these tables.

## Consequences

- Date-range and capacity queries use indexes instead of post meta scans.
- Booking creation can reserve seats and insert rows in one transaction.
- The schema needs its own install and upgrade path, and uninstall cleanup must drop the tables.
- These entities are not available through `WP_Query`; list screens use repositories and `WP_List_Table`.
- Event titles are joined from `wp_posts` when a list needs them.

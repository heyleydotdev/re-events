# CSV and CLI

## Export

```text
wp re-events export events --file=events.csv
wp re-events export venues --file=venues.csv
wp re-events export bookings --file=bookings.csv
```

Without `--file` the CSV is written to standard output.

Event and venue exports include every post status. Bookings are export-only.

## Import

```text
wp re-events import events events.csv
wp re-events import venues venues.csv
```

- A row with an `id` updates that record; an empty `id` creates a new one.
- Event and venue imports use the same columns as the export.
- `organizer_ids`, `exdates`, `rdates`, `timeslots` and `online_urls` are JSON arrays.
- `categories` and `tags` are comma-separated names.
- `post_status` accepts `publish`, `pending` or `draft`.
- Import errors are reported per row; valid rows still import.

## Occurrences

```text
wp re-events expand-occurrences
wp re-events expand-occurrences --event=68
```

Rebuilds stored occurrences for every event or one event.

# Feeds and structured data

## iCal

All published events:

```text
/?reevents_ical=all
```

One event:

```text
/?reevents_ical=123
```

Private events need the feed token from **Settings → API → Feed token**:

```text
/?reevents_ical=123&token=your-token
```

The all-events feed only includes published events.

## RSS

```text
/?reevents_rss=all
/?reevents_rss=123
```

## JSON-LD

Published event pages output schema.org `Event` data in `wp_head`.

Disable it with:

```php
add_filter( 'reevents_output_structured_data', '__return_false' );
```

It includes the event name, dates, status, attendance mode, location, organizer, image and ticket offers.

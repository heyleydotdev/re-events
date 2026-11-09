# Examples

Replace `https://example.com` with your site URL. See [auth.md](auth.md) for authentication.

## Health check

```bash
curl https://example.com/wp-json/re-events/v1/ping
```

## List upcoming events

```bash
curl "https://example.com/wp-json/re-events/v1/events?upcoming=1&per_page=10"
```

## Get one event

```bash
curl https://example.com/wp-json/re-events/v1/events/68
```

## List occurrences

```bash
curl "https://example.com/wp-json/re-events/v1/events/68/occurrences?per_page=50"
```

## Create an event

```bash
curl -u "editor:xxxx xxxx xxxx xxxx xxxx xxxx" \
  -X POST https://example.com/wp-json/re-events/v1/events \
  -H "Content-Type: application/json" \
  -d '{
    "title": "API event",
    "start_utc": "2026-12-01 18:00:00",
    "end_utc": "2026-12-01 20:00:00",
    "timezone": "Europe/London",
    "rrule": "FREQ=WEEKLY;COUNT=4"
  }'
```

## Book a seat

Public booking creation:

```bash
curl -X POST https://example.com/wp-json/re-events/v1/events/68/bookings \
  -H "Content-Type: application/json" \
  -d '{
    "ticket_id": 1,
    "quantity": 1,
    "attendees": [
      { "name": "Jane", "email": "jane@example.com" }
    ]
  }'
```

Read the booking with the reference from the response:

```bash
curl "https://example.com/wp-json/re-events/v1/bookings/21?reference=REFERENCE"
```

## JavaScript

```js
const response = await fetch('/wp-json/re-events/v1/events?per_page=5');
const events = await response.json();
console.log(events);
```

## PHP

```php
$response = wp_remote_get( rest_url( 're-events/v1/events' ) );

if ( ! is_wp_error( $response ) ) {
    $events = json_decode( wp_remote_retrieve_body( $response ), true );
}
```

Authenticated write:

```php
$response = wp_remote_post(
    rest_url( 're-events/v1/events' ),
    [
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode( 'editor:app password' ),
            'Content-Type'  => 'application/json',
        ],
        'body'    => wp_json_encode(
            [
                'title'     => 'PHP event',
                'start_utc' => '2026-12-01 18:00:00',
            ]
        ),
    ]
);
```

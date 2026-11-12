# Hooks

Actions and filters a site or extension can use. Every hook is stable within the current major version.

## Actions

### `reevents_booking_created`

`do_action( 'reevents_booking_created', int $booking_id )`

Fires after a booking is committed and its seats are reserved. Mail and integrations use this for new bookings.

### `reevents_booking_status_changed`

`do_action( 'reevents_booking_status_changed', int $booking_id, string $status )`

Fires after a status change is committed. `$status` is `pending`, `confirmed`, `cancelled` or `rejected`.

### `reevents_register_gateways`

`do_action( 'reevents_register_gateways', string $registry )`

Fires after the built-in gateways register. Call `ReEvents\Services\Gateways\Gateway_Registry::register( $gateway )` to add one.

## Filters

### `reevents_admin_dropdown_limit`

`apply_filters( 'reevents_admin_dropdown_limit', 200 )`

Maximum venues, organizers and categories in admin selects.

### `reevents_venue_dropdown_limit`

`apply_filters( 'reevents_venue_dropdown_limit', 500 )`

Maximum venues in the front-end search filter.

### `reevents_client_ip`

`apply_filters( 'reevents_client_ip', string $ip )`

Overrides the address used by the rate limiter. Use it behind a trusted proxy. Settings → General → Client IP does the same for Cloudflare and `X-Forwarded-For` headers.

### `reevents_email_format`

`apply_filters( 'reevents_email_format', 'auto' )`

Return `html` or `plain` to force the booking mail format. `auto` sends HTML unless a known site-wide email designer is active, then plain text. See [Emails](../emails.md).

### `reevents_geocode_venue`

`apply_filters( 'reevents_geocode_venue', bool $enabled, int $venue_id )`

Return `false` to skip geocoding when a venue is saved.

### `reevents_output_structured_data`

`apply_filters( 'reevents_output_structured_data', bool $enabled )`

Return `false` to stop the schema.org `Event` output on event pages.

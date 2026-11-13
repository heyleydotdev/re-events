# Public API function reference

Generated from PHPDoc in `src/api.php`. Do not edit by hand.

## `reevents_api_version()`

Return the public API version.

**Since:** 1.0.0

## `reevents_cancel_booking()`

Cancel a booking through the self-service rules.

A successful payment is refunded first when refunds are enabled.

**Parameters**

- `int $booking_id` — Booking ID.
- `array<string,mixed> $credentials` — `key`, or `reference` and `email`.

**Returns:** `array<string,mixed>|\WP_Error`

**Since:** 1.0.0

## `reevents_create_booking()`

Create a booking.

Public, matching the REST booking endpoint. Pass a unique `booking_token`
to make retries return the booking that was already created.

**Parameters**

- `array<string,mixed> $data` — Booking payload.

**Returns:** `array<string,mixed>|\WP_Error`

**Since:** 1.0.0

## `reevents_create_category()`

Create an event category.

Requires `edit_reevents_events`.

**Parameters**

- `array<string,mixed> $data` — Category fields.

**Returns:** `array<string,mixed>|\WP_Error`

**Since:** 1.0.0

## `reevents_create_event()`

Create an event.

Requires `publish_reevents_events`.

**Parameters**

- `array<string,mixed> $data` — Event fields.

**Returns:** `array<string,mixed>|\WP_Error`

**Since:** 1.0.0

## `reevents_create_organizer()`

Create an organizer.

Requires `publish_reevents_organizers`.

**Parameters**

- `array<string,mixed> $data` — Organizer fields.

**Returns:** `array<string,mixed>|\WP_Error`

**Since:** 1.0.0

## `reevents_create_ticket()`

Create a ticket.

Requires edit access to the event or `reevents_manage_bookings`.

**Parameters**

- `int $event_id` — Event ID.
- `array<string,mixed> $data` — Ticket fields.

**Returns:** `array<string,mixed>|\WP_Error`

**Since:** 1.0.0

## `reevents_create_venue()`

Create a venue.

Requires `publish_reevents_venues`.

**Parameters**

- `array<string,mixed> $data` — Venue fields.

**Returns:** `array<string,mixed>|\WP_Error`

**Since:** 1.0.0

## `reevents_delete_category()`

Delete an event category.

Requires `edit_reevents_events`.

**Parameters**

- `int $category_id` — Category term ID.

**Returns:** `array<string,mixed>|\WP_Error`

**Since:** 1.0.0

## `reevents_delete_event()`

Delete an event.

Requires delete access to the event.

**Parameters**

- `int $event_id` — Event ID.

**Returns:** `array<string,mixed>|\WP_Error`

**Since:** 1.0.0

## `reevents_delete_organizer()`

Delete an organizer.

Requires delete access to the organizer.

**Parameters**

- `int $organizer_id` — Organizer ID.

**Returns:** `array<string,mixed>|\WP_Error`

**Since:** 1.0.0

## `reevents_delete_ticket()`

Delete a ticket.

Requires edit access to the event or `reevents_manage_bookings`.

**Parameters**

- `int $ticket_id` — Ticket ID.

**Returns:** `array<string,mixed>|\WP_Error`

**Since:** 1.0.0

## `reevents_delete_venue()`

Delete a venue.

Requires delete access to the venue.

**Parameters**

- `int $venue_id` — Venue ID.

**Returns:** `array<string,mixed>|\WP_Error`

**Since:** 1.0.0

## `reevents_get_booking()`

Read one booking.

Requires edit access to the event or `reevents_manage_bookings`.

**Parameters**

- `int $booking_id` — Booking ID.

**Returns:** `array<string,mixed>|\WP_Error`

**Since:** 1.0.0

## `reevents_get_booking_attendees()`

Read attendees for a booking.

Requires edit access to the event or `reevents_manage_bookings`.

**Parameters**

- `int $booking_id` — Booking ID.

**Returns:** `array<int,array<string,mixed>>|\WP_Error`

**Since:** 1.0.0

## `reevents_get_booking_rules()`

Read what the caller may do with a booking.

Credentials may be a signed `key`, or `reference` plus `email`. Event
managers and the booking owner need no credentials.

**Parameters**

- `int $booking_id` — Booking ID.
- `array<string,mixed> $credentials` — `key`, or `reference` and `email`.

**Returns:** `array<string,mixed>|\WP_Error`

**Since:** 1.0.0

## `reevents_get_categories()`

Read event categories.

**Parameters**

- `array<string,mixed> $args` — `search`, `per_page`, `page`.

**Returns:** `array<int,array<string,mixed>>`

**Since:** 1.0.0

## `reevents_get_category()`

Read one event category.

**Parameters**

- `int $category_id` — Category term ID.

**Returns:** `array<string,mixed>|\WP_Error`

**Since:** 1.0.0

## `reevents_get_event()`

Read one event.

**Parameters**

- `int $event_id` — Event ID.

**Returns:** `array<string,mixed>|\WP_Error`

**Since:** 1.0.0

## `reevents_get_event_bookings()`

Read bookings for an event.

Requires edit access to the event or `reevents_manage_bookings`.

**Parameters**

- `int $event_id` — Event ID.
- `array<string,mixed> $args` — `status`, `per_page`, `page`.

**Returns:** `array<int,array<string,mixed>>|\WP_Error`

**Since:** 1.0.0

## `reevents_get_event_occurrences()`

Read stored occurrences for an event.

**Parameters**

- `int $event_id` — Event ID.
- `array<string,mixed> $args` — `limit` and `offset`.

**Returns:** `array<int,array<string,mixed>>|\WP_Error`

**Since:** 1.0.0

## `reevents_get_event_tickets()`

Read tickets for an event.

**Parameters**

- `int $event_id` — Event ID.
- `string $status` — Status for users who may edit the event.

**Returns:** `array<int,array<string,mixed>>`

**Since:** 1.0.0

## `reevents_get_events()`

Read published events.

**Parameters**

- `array<string,mixed> $args` — Arguments accepted by Event_Query.

**Returns:** `array<int,array<string,mixed>>`

**Since:** 1.0.0

## `reevents_get_my_bookings()`

Read the current user's bookings.

Requires a logged-in user and self-service enabled. Each item adds
`event_url`, `start_utc`, `ticket_name`, `total_formatted`, `attendees`
and the self-service `rules` fields.

**Parameters**

- `array<string,mixed> $args` — `per_page` (max 50, default 10), `page`, `status`, `search`.

**Returns:** `array<int,array<string,mixed>>|\WP_Error`

**Since:** 1.0.0

## `reevents_get_organizer()`

Read one organizer.

**Parameters**

- `int $organizer_id` — Organizer ID.

**Returns:** `array<string,mixed>|\WP_Error`

**Since:** 1.0.0

## `reevents_get_organizers()`

Read published organizers.

**Parameters**

- `array<string,mixed> $args` — Arguments accepted by Organizer_Repository::query().

**Returns:** `array<int,array<string,mixed>>`

**Since:** 1.0.0

## `reevents_get_settings()`

Read public plugin settings.

Requires `reevents_manage_settings`.

**Returns:** `array<string,mixed>|\WP_Error`

**Since:** 1.0.0

## `reevents_get_ticket()`

Read one ticket.

**Parameters**

- `int $ticket_id` — Ticket ID.

**Returns:** `array<string,mixed>|\WP_Error`

**Since:** 1.0.0

## `reevents_get_venue()`

Read one venue.

**Parameters**

- `int $venue_id` — Venue ID.

**Returns:** `array<string,mixed>|\WP_Error`

**Since:** 1.0.0

## `reevents_get_venues()`

Read published venues.

**Parameters**

- `array<string,mixed> $args` — Arguments accepted by Venue_Repository::query().

**Returns:** `array<int,array<string,mixed>>`

**Since:** 1.0.0

## `reevents_lookup_booking()`

Find a booking with its reference and attendee email.

Requires self-service and guest lookup to be enabled.

**Parameters**

- `string $reference` — Booking reference.
- `string $email` — Email used for the booking.

**Returns:** `array<string,mixed>|\WP_Error`

**Since:** 1.0.0

## `reevents_reactivate_booking()`

Restore a cancelled free booking through the self-service rules.

**Parameters**

- `int $booking_id` — Booking ID.
- `array<string,mixed> $credentials` — `key`, or `reference` and `email`.

**Returns:** `array<string,mixed>|\WP_Error`

**Since:** 1.0.0

## `reevents_sync_event_occurrences()`

Rebuild stored occurrences for an event.

Requires edit access to the event.

**Parameters**

- `int $event_id` — Event ID.

**Returns:** `array<int,array<string,mixed>>|\WP_Error`

**Since:** 1.0.0

## `reevents_update_booking_status()`

Update a booking status.

Requires edit access to the event or `reevents_manage_bookings`.

**Parameters**

- `int $booking_id` — Booking ID.
- `string $status` — New status.

**Returns:** `array<string,mixed>|\WP_Error`

**Since:** 1.0.0

## `reevents_update_category()`

Update an event category.

Requires `edit_reevents_events`.

**Parameters**

- `int $category_id` — Category term ID.
- `array<string,mixed> $data` — Category fields.

**Returns:** `array<string,mixed>|\WP_Error`

**Since:** 1.0.0

## `reevents_update_event()`

Update an event.

Requires edit access to the event.

**Parameters**

- `int $event_id` — Event ID.
- `array<string,mixed> $data` — Event fields.

**Returns:** `array<string,mixed>|\WP_Error`

**Since:** 1.0.0

## `reevents_update_organizer()`

Update an organizer.

Requires edit access to the organizer.

**Parameters**

- `int $organizer_id` — Organizer ID.
- `array<string,mixed> $data` — Organizer fields.

**Returns:** `array<string,mixed>|\WP_Error`

**Since:** 1.0.0

## `reevents_update_ticket()`

Update a ticket.

Requires edit access to the event or `reevents_manage_bookings`.

**Parameters**

- `int $ticket_id` — Ticket ID.
- `array<string,mixed> $data` — Ticket fields.

**Returns:** `array<string,mixed>|\WP_Error`

**Since:** 1.0.0

## `reevents_update_venue()`

Update a venue.

Requires edit access to the venue.

**Parameters**

- `int $venue_id` — Venue ID.
- `array<string,mixed> $data` — Venue fields.

**Returns:** `array<string,mixed>|\WP_Error`

**Since:** 1.0.0

# Public PHP API

Version: **1.0.0**

ReEvents exposes PHP functions for themes and plugins. They use the same services and repositories as the REST API, but they do not dispatch REST requests and do not render HTML.

Functions run as the current user. Server-side PHP calls do not need a nonce. Nonces remain an HTTP and REST concern. API keys remain REST-only.

The functions do not read the Settings → API switches, rate limits or CORS settings. Those guard HTTP requests; server-side code is already inside WordPress.

See [Public API reference](public-api-reference.md) for generated signatures.

## Events

| Function | Permission |
| --- | --- |
| `reevents_get_events( array $args = [] ): array` | Public, published only |
| `reevents_get_event( int $id ): array\|WP_Error` | Published or `edit_post` |
| `reevents_create_event( array $data ): array\|WP_Error` | `publish_reevents_events` |
| `reevents_update_event( int $id, array $data ): array\|WP_Error` | `edit_post` |
| `reevents_delete_event( int $id ): array\|WP_Error` | `delete_post` |
| `reevents_get_event_occurrences( int $id, array $args = [] ): array\|WP_Error` | Event read access |
| `reevents_sync_event_occurrences( int $id ): array\|WP_Error` | `edit_post` |

`reevents_get_events()` accepts `per_page` (max 100, default 10), `page`, `search`, `category`, `tag`, `venue`, `organizer`, `from`, `to`, `order` (`asc` or `desc`) and `upcoming`. `from` and `to` are `Y-m-d` dates in the site time zone. It returns the current page only; query `Event_Query::query()` directly when you need the total.

Event fields: `title`, `start_utc`, `end_utc`, `timezone`, `all_day`, `rrule`, `exdates`, `rdates`, `timeslots`, `venue_id`, `organizer_ids`, `capacity`, `online_urls`, `status`.

## Venues

| Function | Permission |
| --- | --- |
| `reevents_get_venues( array $args = [] ): array` | Public, published only |
| `reevents_get_venue( int $id ): array\|WP_Error` | Published or `edit_post` |
| `reevents_create_venue( array $data ): array\|WP_Error` | `publish_reevents_venues` |
| `reevents_update_venue( int $id, array $data ): array\|WP_Error` | `edit_post` |
| `reevents_delete_venue( int $id ): array\|WP_Error` | `delete_post` |

Venue fields: `title`, `description`, `address`, `lat`, `lng`, `website`.

## Organizers

| Function | Permission |
| --- | --- |
| `reevents_get_organizers( array $args = [] ): array` | Public, published only |
| `reevents_get_organizer( int $id ): array\|WP_Error` | Published or `edit_post` |
| `reevents_create_organizer( array $data ): array\|WP_Error` | `publish_reevents_organizers` |
| `reevents_update_organizer( int $id, array $data ): array\|WP_Error` | `edit_post` |
| `reevents_delete_organizer( int $id ): array\|WP_Error` | `delete_post` |

Organizer fields: `title`, `description`.

## Categories

| Function | Permission |
| --- | --- |
| `reevents_get_categories( array $args = [] ): array` | Public |
| `reevents_get_category( int $id ): array\|WP_Error` | Public |
| `reevents_create_category( array $data ): array\|WP_Error` | `edit_reevents_events` |
| `reevents_update_category( int $id, array $data ): array\|WP_Error` | `edit_reevents_events` |
| `reevents_delete_category( int $id ): array\|WP_Error` | `edit_reevents_events` |

Category fields: `name`, `description`, `parent`.

## Tickets

| Function | Permission |
| --- | --- |
| `reevents_get_event_tickets( int $event_id, string $status = 'active' ): array` | Event read access |
| `reevents_get_ticket( int $id ): array\|WP_Error` | Event read access |
| `reevents_create_ticket( int $event_id, array $data ): array\|WP_Error` | `edit_post` or `reevents_manage_bookings` |
| `reevents_update_ticket( int $id, array $data ): array\|WP_Error` | `edit_post` or `reevents_manage_bookings` |
| `reevents_delete_ticket( int $id ): array\|WP_Error` | `edit_post` or `reevents_manage_bookings` |

Ticket fields: `name`, `description`, `price_minor`, `currency`, `capacity`, `min_per_booking`, `max_per_booking`, `sale_start_utc`, `sale_end_utc`, `status`.

`reevents_get_event_tickets()` returns an empty array when the caller cannot read the event. The status argument is ignored unless the caller may edit the event.

## Bookings

| Function | Permission |
| --- | --- |
| `reevents_get_event_bookings( int $event_id, array $args = [] ): array\|WP_Error` | `edit_post` or `reevents_manage_bookings` |
| `reevents_get_booking( int $id ): array\|WP_Error` | `edit_post` or `reevents_manage_bookings` |
| `reevents_get_booking_attendees( int $id ): array\|WP_Error` | `edit_post` or `reevents_manage_bookings` |
| `reevents_create_booking( array $data ): array\|WP_Error` | Public, same as REST |
| `reevents_update_booking_status( int $id, string $status ): array\|WP_Error` | `edit_post` or `reevents_manage_bookings` |
| `reevents_get_my_bookings( array $args = [] ): array\|WP_Error` | Logged-in owner, self-service on |
| `reevents_lookup_booking( string $reference, string $email ): array\|WP_Error` | Reference + email, guest lookup on |
| `reevents_get_booking_rules( int $id, array $credentials = [] ): array\|WP_Error` | Owner, manager, signed key or reference + email |
| `reevents_cancel_booking( int $id, array $credentials = [] ): array\|WP_Error` | Owner, manager, signed key or reference + email |
| `reevents_reactivate_booking( int $id, array $credentials = [] ): array\|WP_Error` | Owner, manager, signed key or reference + email |

Booking creation fields: `event_id`, `ticket_id`, `occurrence_id`, `quantity`, `attendees`, `booking_token`. Use one token per form load so retries return the existing booking. Paid bookings add `checkout_url` when a gateway is configured. The token is remembered for five minutes, matching the REST endpoint, so a much later retry creates a new booking.

`reevents_get_event_bookings()` accepts `status`, `per_page` (max 100, default 20) and `page`.

There is no delete helper. Cancel or reject the booking instead.

The self-service helpers add `event_url`, `start_utc`, `ticket_name`, `total_formatted`, `attendees`, `can_cancel`, `can_reactivate`, `refund_on_cancel`, `cancel_reason` and `reactivate_reason` to the booking row. `reevents_get_my_bookings()` accepts `per_page` (max 50, default 10), `page`, `status` and `search`. The credential argument takes a signed `key`, or `reference` plus `email`; the owner and event managers need none. The rules follow **Settings → Bookings → Self-service**, and a paid cancellation is refunded first when refunds are enabled.

## Settings

`reevents_get_settings(): array|WP_Error` returns `timezone`, `time_display`, `date_format` and `time_format`. Requires `reevents_manage_settings`.

## Return values

- Reads return arrays shaped like the REST responses.
- Deletes return `['deleted' => true, 'previous' => array]`.
- Errors return `WP_Error` with a `status` data value.
- Functions run as the current user; set the user before calling.

## Examples

Create an event:

```php
$event = reevents_create_event(
	[
		'title'     => 'Community meetup',
		'start_utc' => '2026-08-12 18:00:00',
		'end_utc'   => '2026-08-12 20:00:00',
		'timezone'  => 'Europe/Berlin',
	]
);

if ( is_wp_error( $event ) ) {
	return;
}

$ticket = reevents_create_ticket(
	(int) $event['id'],
	[
		'name'        => 'General admission',
		'price_minor' => 1500,
		'currency'    => 'EUR',
	]
);
```

Create a booking:

```php
$booking = reevents_create_booking(
	[
		'event_id'      => 123,
		'ticket_id'     => 456,
		'quantity'      => 1,
		'attendees'     => [
			[
				'name'  => 'Jane Doe',
				'email' => 'jane@example.com',
			],
		],
		'booking_token' => wp_generate_uuid4(),
	]
);
```

## Deprecation policy

- Public functions carry `@since`.
- Breaking changes wait for a major API version.
- Deprecated functions stay for one major version and call `_deprecated_function()`.
- `@deprecated` names the replacement and version.

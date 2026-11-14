# My bookings

The My Bookings widget lets visitors see their own bookings and change their RSVP. It ships as the `[reevents_bookings]` shortcode, the **My Bookings** block and four REST routes. All three surfaces use the same rules service, so behaviour cannot drift.

## Access modes

| Caller | Credential | Sees |
| --- | --- | --- |
| Logged-in owner | `bookings.user_id` | Every booking on the account |
| Email link | Signed key in the link | That one booking |
| Guest lookup | Booking reference + attendee email | That one booking |

The signed key is `HMAC-SHA256( id "|" reference, wp_salt( 'auth' ) )`. It is scoped to one booking, cannot list bookings and changes if the reference changes.

Guest lookups are rate limited to 10 per minute per IP across the form and REST. A wrong reference and a wrong email return the same message, so references cannot be probed for existence.

## Rules

`ReEvents\Services\Booking_Self_Service` is the only place the rules live:

| Action | Conditions |
| --- | --- |
| Cancel | Status `pending` or `confirmed`, before `start - cutoff` |
| Refund | A `succeeded` payment is refunded through `Payment_Service::refund_booking()` before the booking cancels |
| Restore | Status `cancelled`, `total_minor = 0`, before the cutoff |

- The occurrence start wins over the event start when a booking has one.
- A failed refund aborts the cancel and the payment and booking stay as they were.
- A booking with a payment still processing cannot be changed until the provider settles it, so a late payment cannot revive a cancelled booking.
- Paid bookings are never restored: they would need a new charge, so the page points at the event instead.
- Missing events, deleted tickets and sold-out occurrences produce a blocked action, never a fatal.

## Settings

**Settings → Bookings → Self-service**

| Key | Default | Meaning |
| --- | --- | --- |
| `self_service` | on | Master switch for the list and the actions |
| `self_service_cutoff` | `0` | Hours before the start when changes close (0–720) |
| `self_service_guest` | on | Allow reference + email lookup |
| `self_service_refund` | on | Refund a successful payment when the visitor cancels |
| `self_service_page` | empty | Page hosting the widget. Used by `{manage_url}` |

## Email token

`{manage_url}` links to `self_service_page` with `reevents_booking`, `reevents_reference` and `reevents_key`. It is empty when self-service is off or no page is set. Add it to the attendee template under **Settings → Emails**.

## REST

Namespace `re-events/v1`.

| Route | Method | Auth | Body or query |
| --- | --- | --- | --- |
| `/my-bookings` | `GET` | Logged-in owner | `page`, `per_page`, `status`, `search` |
| `/my-bookings/lookup` | `GET` | Reference + email | `reference`, `email` |
| `/my-bookings/{id}` | `GET` | Owner, key, or reference + email | `key` or `reference` + `email` |
| `/my-bookings/{id}` | `PATCH` | Same | `action`: `cancel` or `reactivate` |

Items include `can_cancel`, `can_reactivate`, `refund_on_cancel`, `cancel_reason`, `reactivate_reason`, `start_utc`, `event_url`, `ticket_name`, `total_formatted` and `attendees`, so clients never re-derive the rules.

The routes bypass the public-API switch so guest self-service keeps working on sites that lock the API down, but they still respect `api_enabled` and carry their own rate limit.

### Examples

```bash
# Guest lookup
curl -X POST https://example.com/wp-json/re-events/v1/my-bookings/lookup \
  -H 'Content-Type: application/json' \
  -d '{"reference":"BOOKING_REFERENCE","email":"jane@example.com"}'

# Owner cancel with a signed key
curl -X PATCH https://example.com/wp-json/re-events/v1/my-bookings/42 \
  -H 'Content-Type: application/json' \
  -d '{"key":"SIGNED_KEY","action":"cancel"}'
```

## Templates and markup

The template is `templates/bookings.php` and can be overridden in `re-events/bookings.php` in the theme.

| Class | Element |
| --- | --- |
| `.reevents-bookings-wrapper` | Outer wrapper |
| `.reevents-bookings` | Inner wrapper |
| `.reevents-bookings-heading` | Heading |
| `.reevents-bookings-list` | Booking list |
| `.reevents-booking` | One booking, with a `reevents-booking-status-{status}` modifier |
| `.reevents-booking-event` | Event title and link |
| `.reevents-booking-when` | Start date and time |
| `.reevents-booking-ticket` | Ticket name |
| `.reevents-booking-total` | Formatted total |
| `.reevents-booking-status` | Status label |
| `.reevents-booking-reference` | Reference |
| `.reevents-booking-action` | Action form |
| `.reevents-booking-cancel` | Cancel button |
| `.reevents-booking-reactivate` | Restore button |
| `.reevents-booking-note` | Why an action is unavailable |
| `.reevents-bookings-lookup` | Guest lookup form |
| `.reevents-bookings-notice` | Result notice, `role="status"` |

Actions are plain `POST` forms to `admin-post.php` with a nonce and hidden credentials, so the widget works without JavaScript.

Long lists page with `limit` (bookings per page) plus `paginate` and `pagination_key`. The page argument is `reevents_bookings_page`, or `reevents_bookings_page_{key}` when a key is set, so several lists can share a page. A successful action redirects back to the same page with `reevents_booking_result` and an optional `reevents_booking_reason`.

## Limits

- Quantity, ticket type and occurrence cannot be changed from the front end. That needs a payment-delta flow.
- A booking made as a guest appears to the account only through the lookup form; it is not attached to a user later.
- The signed key has no expiry; it stops working when the booking reference or the site salt changes.

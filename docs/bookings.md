# Bookings

Events with timeslots show a time selector on the booking form. Each slot has its own capacity and keeps its own seat count.

- A slot is bookable only while it is scheduled and in the future.
- A slot capacity of `0` means unlimited, matching ticket capacity.
- Cancelling or rejecting a booking releases both the ticket seats and the slot seats.
- Events without timeslots keep the existing single-capacity booking flow.

Slot capacity is reserved inside the same database transaction as the ticket seats, so two checkouts cannot take the last seat.

## Self-service

Visitors can see their own bookings and change their RSVP with the `[reevents_bookings]` shortcode or the **My Bookings** block. Logged-in users see every booking on their account; guests can find one with the reference and the email used to book, or follow the `{manage_url}` link in their email.

- Cancelling before the cutoff releases the seats. A successful payment is refunded first when **Refunds** is on.
- A booking whose payment is still processing cannot be changed until the provider settles it.
- A cancelled free booking can be reserved again while seats remain.
- Changes close a configurable number of hours before the start, set under **Settings → Bookings → Self-service**.
- The REST routes under `/wp-json/re-events/v1/my-bookings` expose the same list, lookup and actions.

See [My bookings](/developer/my-bookings.md) for the rules, access modes and markup.

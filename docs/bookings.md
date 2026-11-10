# Bookings

Events with timeslots show a time selector on the booking form. Each slot has its own capacity and keeps its own seat count.

- A slot is bookable only while it is scheduled and in the future.
- A slot capacity of `0` means unlimited, matching ticket capacity.
- Cancelling or rejecting a booking releases both the ticket seats and the slot seats.
- Events without timeslots keep the existing single-capacity booking flow.

Slot capacity is reserved inside the same database transaction as the ticket seats, so two checkouts cannot take the last seat.

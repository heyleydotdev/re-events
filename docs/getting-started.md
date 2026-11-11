# Getting started

ReEvents adds events, venues, organizers, tickets and bookings to WordPress. There is no setup wizard; four screens and a shortcode or block are enough.

## 1. Settings

Open **ReEvents → Settings**.

- **General:** default event timezone, time display (site, event or visitor), map provider and Google Maps key.
- **Formats:** date and time formats used by every renderer.
- **Booking rules:** require approval, unpaid hold window, custom attendee fields.
- **Payments:** gateway, default currency, Stripe or PayPal credentials.
- **Emails:** admin and attendee templates with placeholders.
- **API:** enable the REST API, public reads, rate limit, CORS origins, feed token.

## 2. Content

1. **Venues** — title, address, coordinates and website. Without coordinates the venue is geocoded on save.
2. **Organizers** — title, description and organizer types.
3. **Events** — title, description, featured image, start and end, timezone, recurrence rule, timeslots and online links.
4. **Tickets** — add them in the **Tickets** box on the event screen: price, currency, capacity, minimum and maximum seats, sale window and status.

An event without an active ticket shows “No tickets are available.” in the booking form.

## 3. Output

Use a block, a shortcode or your theme:

- **Blocks:** Events List, Event Details, Venues List, Events Search, Calendar, Map, Event Field, Booking Form.
- **Shortcodes:** `[reevents_events]`, `[reevents_event]`, `[reevents_venues]`, `[reevents_search]`, `[reevents_calendar]`, `[reevents_map]`, `[reevents_booking_form]`, `[reevents_field]`.
- **Theme:** call the renderers or the [public PHP API](api/public-api.md).

For custom layouts, place field shortcodes next to each other instead of hiding parts of `[reevents_event]`:

```text
[reevents_field field="title" tag="h2" link="1"]
[reevents_field field="start"]
[reevents_field field="venue_address" label="Where:"]
[reevents_field field="price_from"]
```

## 4. Bookings

Visitors submit the booking form without an account. Free bookings confirm immediately; paid bookings stay pending until the gateway confirms. The attendee receives the confirmation email, the admin address gets the booking email, and both can be customized per event.

Manage bookings under **ReEvents → Bookings**: view attendees, resend mail, cancel, refund, export CSV.

## 5. Feeds and privacy

- iCal and RSS feeds are documented in [Feeds and structured data](developer/feeds.md).
- The plugin registers attendee data in **Tools → Export Personal Data** and **Tools → Erase Personal Data**.
- Shortcodes, blocks and builders are documented in [Shortcodes](shortcodes/README.md) and [Render contract](developer/render-contract.md).

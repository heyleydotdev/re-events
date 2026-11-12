# Getting started

ReEvents adds events, venues, organizers, tickets and bookings to WordPress. There is no setup wizard; the admin screens plus a shortcode or block are enough.

## 1. Settings

Open **ReEvents → Settings**.

- **General:** default event timezone, time display (site, event or visitor), map provider, Google Maps key and the trusted-proxy client IP option for rate limits.
- **Formats:** date and time formats used by every renderer.
- **Booking rules:** require approval, unpaid hold window, custom attendee fields.
- **Payments:** gateway, default currency, Stripe or PayPal credentials.
- **Emails:** sender and recipients, admin and attendee templates with placeholders, HTML layout and lifecycle mail.
- **API:** enable the REST API, public reads, rate limit, CORS origins, feed token.

## 2. Content

1. **Venues** — title, address, coordinates and website. Without coordinates the venue is geocoded on save.
2. **Organizers** — title, description and organizer types.
3. **Events** — title, description, featured image, start and end, timezone, recurrence rule, timeslots and online links.
4. **Tickets** — add them in the **Tickets** box on the event screen: price, currency, capacity, minimum and maximum seats, sale window and status. Sale dates are typed in the event's time zone and stored in UTC. **Add free ticket** pre-fills an RSVP with price 0 and the event capacity.

An event without an active ticket shows “No tickets are available.” in the booking form.

## 3. Output

Use a block, a shortcode or your theme:

- **Blocks:** Events List, Event Details, Venues List, Events Search, Calendar, Map, Event Field, Booking Form.
- **Shortcodes:** `[reevents_events]`, `[reevents_event]`, `[reevents_venues]`, `[reevents_search]`, `[reevents_calendar]`, `[reevents_map]`, `[reevents_booking_form]`, `[reevents_field]`.
- **Theme:** call the renderers or the [public PHP API](api/public-api.md).

To put a search form above a separate list, hide the search results and let the list read the query:

```text
[reevents_search show_results="0"]
[reevents_events use_query="1" limit="10"]
```

### Pagination

Event and venue lists paginate on the server. The event list uses `reevents_page`; the venue list uses `reevents_venue_page`. Use `limit` for the page size.

A sidebar or teaser list should not paginate:

```text
[reevents_events limit="5" upcoming="1" paginate="0"]
```

If two paginated lists share a page, give each its own key:

```text
[reevents_events pagination_key="main"]
[reevents_events pagination_key="past" upcoming="0"]
```

Venue lists use `pagination_key="featured"` for a second list. Pagination keeps the current search, category, venue and date filters.

### Filtering and card content

Filter the list by keyword, tag or date, and order it by start date:

```text
[reevents_events search="gala" tag="music" from="2027-01-01" to="2027-12-31" order="asc"]
```

Past events, newest first:

```text
[reevents_events upcoming="0" order="desc" limit="10"]
```

On a category archive, `category="current"` keeps the list inside that category. Venue and organizer archives accept the same value.

Card options are opt-in. This list shows organizers, tags, the end date, remaining seats and a three-word teaser, with the title as an `h2`:

```text
[reevents_events show_organizers="1" show_tags="1" show_end="1" show_seats_left="1" show_excerpt="1" excerpt_length="3" title_tag="h2"]
```

Cancelled events are hidden by default. Add `show_cancelled="1"` to include them with a visible badge:

```text
[reevents_events show_cancelled="1" upcoming="0"]
```

For custom layouts, place field shortcodes next to each other instead of hiding parts of `[reevents_event]`:

```text
[reevents_field field="title" tag="h2" link="1"]
[reevents_field field="start"]
[reevents_field field="venue_address" label="Where:"]
[reevents_field field="price_from"]
```

### Other widgets

The detail, venue, search, calendar, map and booking widgets accept the same kind of options:

```text
[reevents_event id="123" show_image="1" show_price="1" show_categories="1" show_end="1"]
[reevents_venues search="hall" upcoming="1" show_address="1" show_next_event="1"]
[reevents_search show_tag="1" show_organizer="1" show_order="1" paginate="1" show_price="1"]
[reevents_calendar category="12" show_cancelled="1" show_today="1" week_start="sun"]
[reevents_map upcoming="1" center="51.5,-0.12" zoom="11" fit="1" show_address="1"]
[reevents_booking_form event="123" heading="Book a seat" max_quantity="4" hide_sold_out="1"]
```

### Date and time formats

Every widget that prints a date or time accepts a format. Defaults are the site formats; an empty value falls back to the site setting.

```text
[reevents_events date_format="Y-m-d" time_format="H:i"]
[reevents_event date_format="j F Y" time_format="H:i"]
[reevents_calendar month_format="F Y" time_format="H:i"]
[reevents_field field="start" date_format="D, j M Y" time_format="H:i"]
[reevents_booking_form date_format="D, j M Y" time_format="H:i"]
```

Formats use the PHP date format tokens accepted by `wp_date()`.

## 4. Bookings

Visitors submit the booking form without an account. Free bookings confirm immediately; paid bookings stay pending until the gateway confirms. The attendee receives mail when the booking is confirmed, cancelled or rejected; admin recipients get new, cancelled and refunded bookings. Both templates can be customized per event. See [Emails](emails.md).

Manage bookings under **ReEvents → Bookings**: view attendees, resend mail, cancel, refund, export CSV.

## 5. Feeds and privacy

- iCal and RSS feeds are documented in [Feeds and structured data](developer/feeds.md).
- The plugin registers attendee data in **Tools → Export Personal Data** and **Tools → Erase Personal Data**.
- Shortcodes, blocks and builders are documented in [Shortcodes](shortcodes/README.md) and [Render contract](developer/render-contract.md).

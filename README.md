<p align="center">
  <img src="assets/img/logo.png" alt="ReEvents" width="112" height="112" />
</p>

<h1 align="center">ReEvents</h1>

<p align="center"><strong>Events, venues, organizers, tickets, bookings and Stripe/PayPal payments for WordPress.</strong></p>

<p align="center">
  <img alt="WordPress 6.5+" src="https://img.shields.io/badge/WordPress-6.5+-21759B" />
  <img alt="PHP 8.0+" src="https://img.shields.io/badge/PHP-8.0+-777BB4" />
  <img alt="License: GPL-2.0-or-later" src="https://img.shields.io/badge/license-GPL--2.0--or--later-blue" />
</p>

ReEvents adds a complete event domain to WordPress: a calendar, sellable tickets, guest checkout and the admin screens behind them. It ships as shortcodes and Gutenberg blocks, so any theme works and nothing is locked to a page builder.

> [!NOTE]
> ReEvents ships no frontend CSS. Every widget is semantic HTML with stable classes, and your theme owns the design.

## Features

- **Events** — single and multi-day, all-day dates, timeslots, categories, tags, featured images and online event links.
- **Recurrence** — RRULE rules with interval, weekday or monthday settings, excluded dates and extra dates.
- **Venues and organizers** — addresses, coordinates and websites; geocoding on save; OpenStreetMap by default, Google Maps optional.
- **Tickets** — free RSVP and paid tickets with price, currency, capacity, sale window and per-booking limits.
- **Bookings** — guest checkout, optional approval, unpaid holds, atomic seat reservation and cancellation.
- **Payments** — Stripe Checkout and PayPal Orders with refunds and signed webhooks.
- **Admin** — booking and attendee tables, check-in, resend email, editable mail templates and CSV export.
- **Output** — shortcodes, Gutenberg blocks and a PHP CRUD API; no CSS to override.
- **Integrations** — REST API with OpenAPI docs, WP-CLI commands, CSV import/export, iCal/RSS feeds, add-to-calendar links and schema.org data.
- **Privacy** — attendee data is exposed to the WordPress personal data exporter and eraser, and uninstall can remove all plugin data.

## Requirements

- WordPress 6.5 or newer (tested up to 7.1)
- PHP 8.0 or newer
- A database supported by WordPress (MySQL or MariaDB)

## Installation

1. Upload the `re-events` folder to `/wp-content/plugins/`, or install the release zip.
2. Activate **ReEvents** in **Plugins**.
3. Open **ReEvents → Settings** and set the event timezone, currency and payment gateway.
4. Create a venue, an event and its tickets, then place a block or a shortcode on a page.

```bash
wp plugin install re-events.zip --activate
```

## Display events

The event list is one shortcode:

```text
[reevents_events limit="5"]
```

The same widgets are available as blocks: Events List, Event Details, Venues List, Events Search, Calendar, Map, Event Field and Booking Form.

For custom layouts, compose single fields instead of styling around the event template:

```text
[reevents_field field="title" tag="h2" link="1"]
[reevents_field field="start"]
[reevents_field field="venue_address" label="Where:"]
[reevents_field field="price_from"]
```

### Shortcodes and blocks

| Output           | Shortcode                 | Block                       |
| ---------------- | ------------------------- | --------------------------- |
| Event list       | `[reevents_events]`       | `reevents/events-list`      |
| Event details    | `[reevents_event]`        | `reevents/event-details`    |
| Venue list       | `[reevents_venues]`       | `reevents/venues-list`      |
| Search and filters | `[reevents_search]`     | `reevents/events-search`    |
| Calendar         | `[reevents_calendar]`     | `reevents/calendar`         |
| Map              | `[reevents_map]`          | `reevents/map`              |
| Booking form     | `[reevents_booking_form]` | `reevents/booking-form`     |
| Single field     | `[reevents_field]`        | `reevents/field`            |

## Payments

Choose **Stripe**, **PayPal** or **None** under **ReEvents → Settings → Payments**, then add the provider credentials. Stripe uses Checkout Sessions, PayPal uses Orders v2, and both confirm bookings through signed webhooks.

Point your provider's webhook at:

```text
https://example.com/wp-json/re-events/v1/webhooks/stripe
https://example.com/wp-json/re-events/v1/webhooks/paypal
```

Full setup and test steps are in [Payments](docs/payments.md).

## For developers

**PHP API** — CRUD functions for events, tickets and bookings that run as the current user and return arrays or `WP_Error`:

```php
$events = reevents_get_events(
	[
		'per_page' => 5,
		'upcoming' => true,
	]
);
```

**REST API** — every route is documented with a generated OpenAPI schema:

```bash
curl "https://example.com/wp-json/re-events/v1/events?per_page=5"
wp re-events openapi | sed -n '/^{/,$p' > openapi.json
```

**WP-CLI** — imports, exports, self-tests and performance fixtures:

```bash
wp re-events export events --file=events.csv
wp re-events import events events.csv
wp re-events export-attendees --event=68 > attendees.csv
wp re-events self-test
```

**Documentation**

- [Getting started](docs/getting-started.md)
- [Shortcode reference](docs/shortcodes/README.md)
- [Public PHP API](docs/api/public-api.md)
- [Custom booking forms](docs/api/booking-form.md)
- [Payments](docs/payments.md)
- [Bookings and capacity](docs/bookings.md)
- [Maps](docs/maps.md)
- [Hooks](docs/developer/hooks.md)
- [Architecture](docs/developer/architecture.md)
- [Upgrading](docs/upgrading.md)

## Development

Development needs PHP 8.0+, Composer, Bun and Docker for the local WordPress environment.

```bash
composer install
bun install
bun run wp-env start
```

| Command                   | Purpose                                              |
| ------------------------- | ---------------------------------------------------- |
| `bun run build`           | Build the Gutenberg blocks into `blocks/build/`      |
| `composer format`         | Fix PHPCS issues                                     |
| `composer lint`           | Run PHPCS                                            |
| `composer analyze`        | Run PHPStan                                          |
| `composer test`           | Run unit tests                                       |
| `composer test:integration` | Run WordPress integration tests                    |
| `bun run lint:js`         | Lint the block sources                               |
| `bun run test:e2e`        | Run Playwright end-to-end tests (requires wp-env)    |
| `composer release`        | Build the release zip                                |

Integration tests use the WordPress test library; install it with `bin/install-wp-tests.sh`. End-to-end tests and the WP-CLI checks need a running `wp-env`.

### Project layout

| Path         | Contents                                                        |
| ------------ | --------------------------------------------------------------- |
| `src/`       | Domain, data, services, query, REST, render, admin, frontend, CLI |
| `blocks/`    | Gutenberg block sources and build output                        |
| `templates/` | Theme-overridable templates                                     |
| `tests/`     | Unit, integration and Playwright tests                          |
| `docs/`      | User and developer documentation                                |
| `languages/` | Translation template                                            |

---

Licensed under [GPL-2.0-or-later](LICENSE). Copyright © 2026 Relaps. See [TRADEMARK.md](TRADEMARK.md) for name and logo usage.

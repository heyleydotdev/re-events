=== ReEvents ===
Contributors: relaps
Tags: events, event management, events calendar, tickets, bookings
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Events, venues, organizers, tickets, bookings and Stripe/PayPal payments for WordPress.

== Description ==

ReEvents adds an event domain to WordPress without a page builder lock-in.

* Events with recurrence rules, timeslots, all-day dates, online links and featured images.
* Venues with address, coordinates, website and OpenStreetMap or Google Maps output.
* Organizers with descriptions and organizer types.
* Tickets with price, currency, capacity, sale window and per-booking limits.
* Bookings with capacity reservations in a database transaction, approvals and unpaid holds.
* Stripe and PayPal checkout, refunds and signed webhooks.
* Admin booking screens, attendee export, HTML mail templates with status notifications and CSV import/export.
* Shortcodes and Gutenberg blocks for lists, details, search, calendar, map, booking form and single event fields.
* Semantic HTML with no frontend CSS; your theme owns the design.
* REST API, PHP API, iCal/RSS feeds and schema.org output.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate it in **Plugins**.
3. Open **ReEvents → Settings** and set the timezone, currency and payment gateway.
4. Create a venue, an event and its tickets.
5. Place a block or shortcode on a page.

== Frequently Asked Questions ==

= Does it need a page builder? =

No. Shortcodes and blocks work in any theme; page builders can use the shortcodes in a text module.

= Does it ship frontend CSS? =

No. Every widget uses semantic HTML and stable classes so the theme controls styling.

= Can attendees book without an account? =

Yes. Bookings are guest-first. Free bookings confirm immediately; paid bookings wait for the gateway.

= Where is personal data handled? =

Attendee data is available in the WordPress privacy exporter and eraser. Uninstall keeps data unless **Delete data on uninstall** is enabled.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.

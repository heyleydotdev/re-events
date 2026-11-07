=== ReEvents ===
Contributors: re
Tags: events, calendar, event management
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Event management for WordPress with a REST API and a React + shadcn/ui admin.

== Description ==

ReEvents adds event management to WordPress. Events are stored as a custom post type so builders, query loops, SEO plugins, revisions, and media keep working.

The plugin is API-first. A complete REST API will expose event reads and writes. The admin is a React app mounted in a shadow root; the event editor keeps the native WordPress editor.

This release is the scaffold. The admin shell and section navigation are present; the event post type and REST API are not available yet.

== Installation ==

1. Upload the `re-events` directory to `wp-content/plugins/`.
2. Run `composer install` in the plugin directory.
3. If building from source, run `bun install` and `bun run build`.
4. Activate the plugin through the Plugins screen.

== Frequently Asked Questions ==

= Does this plugin render events on the frontend? =

Not yet. Frontend templates and builder integrations are a later milestone.

= Does it require WooCommerce? =

No. WooCommerce integration is planned only for paid tickets.

== Changelog ==

= 0.1.0 =
* Initial scaffold.
* Composer, PHPCS, PHPStan, and PHPUnit tooling.

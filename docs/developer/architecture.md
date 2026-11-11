# Architecture

ReEvents is a WordPress plugin. WordPress supplies the request lifecycle, post types, taxonomies, capabilities, REST plumbing, settings storage, and mail. ReEvents adds the event domain, booking flow, and the screens and endpoints around them.

## Bootstrap

`re-events.php` defines the plugin constants and registers activation and deactivation hooks. It loads `src/plugin.php`, which wires the services on `plugins_loaded`.

`ReEvents\Plugin::boot()` registers:

- post types, taxonomies, meta, and capabilities
- admin screens and assets
- REST controllers
- booking, occurrence, and mail services
- front-end shortcodes, blocks, and assets
- WP-CLI commands

The `ReEvents\` namespace maps to `src/`. Class names map to kebab-case file names, so `ReEvents\Admin\Menu` loads from `src/admin/menu.php`.

## Layers

| Layer | Location | Responsibility |
|---|---|---|
| Data | `src/data` | Post types, taxonomies, meta, schema, repositories |
| Domain | `src/domain` | `Event`, recurrence rules, value objects |
| Services | `src/services` | Booking, occurrence, mail, export, API keys |
| Query | `src/query` | Shared event queries |
| REST | `src/rest` | Routes, controllers, schemas |
| Render | `src/render` | Server-side renderers and formatting |
| Admin | `src/admin` | Screens, tables, settings, meta boxes |
| Frontend | `src/frontend` | Shortcodes, blocks, form handling, assets |
| CLI | `src/cli` | OpenAPI, self-test, benchmark, export |

## Storage

Events, venues, and organizers are custom post types. Their data lives in post fields and registered post meta. Event categories, event tags, and organizer tags are taxonomies.

Occurrences, tickets, bookings, and attendees use custom tables. They are queried directly through repositories because they need indexes, transactions, and capacity checks that post meta cannot provide. See [ADR 0001](adr/0001-custom-tables-for-bookings.md).

The schema is installed and upgraded with `dbDelta` in `src/data/schema.php`.

## Request flow

1. WordPress routes the request.
2. A shortcode, block, widget, REST controller, or admin screen calls a renderer or service.
3. Repositories read or write the domain data.
4. Templates render the result.
5. Assets load only when their surface is present.

REST controllers validate and sanitize request arguments, check capabilities, and return schemas that feed the generated OpenAPI document.

## Bookings

`Booking_Service` owns booking creation, status changes, capacity reservation, and seat release. It runs inside a database transaction and emits `reevents_booking_created` and `reevents_booking_status_changed` for mail and integrations.

`Booking_Emails` composes admin and attendee mail from global templates and per-event overrides. `Attendee_Export` streams CSV.

## API

The plugin exposes `re-events/v1`. Public reads can be disabled in Settings → API. Writes require WordPress authentication or a ReEvents API key.

API keys are stored as HMAC-SHA256 hashes. A key authenticates as its owning user and inherits that user's capabilities.

Plugins and themes also get a PHP CRUD API in `src/api.php`, backed by the same services and repositories. See [public-api.md](../api/public-api.md).

`docs/api/openapi.json` is generated from the registered routes and their schemas. CI regenerates it and fails when the committed file is stale.

## Templates

Templates live in `templates/`. A theme can override a template by placing a file with the same name in a `re-events/` directory in the theme. Renderers call `Template::render()` and never build markup outside the template.

The frontend render contract — widget args, classes, data attributes and style modes — is documented in [render-contract.md](developer/render-contract.md).

## Assets

Admin and front-end assets are registered through `src/admin/assets.php`, `src/frontend/assets.php`, and the screens that need them. Scripts and styles are loaded conditionally, not on every page.

## Testing

- `composer lint` runs PHPCS.
- `composer analyze` runs PHPStan.
- `wp re-events self-test` runs recurrence, query, REST, capacity, and booking checks.
- `wp re-events bench` seeds fixtures, measures the hot paths, and enforces query and time budgets.

## Extension points

- WordPress hooks emitted by booking and status changes.
- Theme template overrides.
- REST API for external clients.
- WP-CLI commands for maintenance and automation.

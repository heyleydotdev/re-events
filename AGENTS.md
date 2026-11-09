# AGENTS.md

## Project

ReEvents is a WordPress event manager plugin. GPL-2.0-or-later, no pro tier, no telemetry.

## Stack

- PHP 8.0+, WordPress 6.5+
- Namespace `ReEvents\` maps to `src/` (kebab-case paths)
- Prefixes: `reevents_` for DB identifiers; `re_events_` / `RE_EVENTS_` for PHP globals; meta keys `_reevents_`
- Text domain: `re-events`
- File names are kebab-case; classes autoload by class name (`ReEvents\Admin\Menu` → `src/admin/menu.php`)
- Tabs, WordPress Coding Standards

## Commands

- `composer install` — install PHP dev tools
- `composer hooks` — install the pre-commit hook (one-time)
- `composer format` — auto-fix PHPCS issues
- `composer lint` — PHPCS
- `composer analyze` — PHPStan
- `composer test` — PHPUnit unit tests
- `composer test:integration` — WordPress integration tests (requires `bin/install-wp-tests.sh`)
- `composer test:coverage` — unit tests with coverage
- `bun install` — install JS dev tools
- `bun run build` — build blocks into `blocks/build/`
- `bun run lint:js` — JS lint
- `bun run wp-env start` — start the local WordPress environment
- `wp re-events openapi > docs/api/openapi.json` — regenerate the API spec from registered routes
- `wp re-events self-test` — run recurrence, query and REST checks
- `wp re-events export-attendees --event=68 > attendees.csv` — export attendee CSV
- `wp re-events bench seed` / `bench run --assert` / `bench clean` — seed, measure and remove performance fixtures

`docs/api/openapi.json` is generated from the registered routes and their schema callbacks: paths, parameters, request bodies and response schemas all come from code. CI regenerates the spec and fails if the committed file is stale.

Release builds use `.distignore` and `composer install --no-dev --optimize-autoloader`; development files are not shipped.

The pre-commit hook formats and lints staged PHP files. It blocks the commit if unfixable errors remain.

## Rules

- Escape output late, sanitize input early; capability + nonce on every write.
- Builder adapters contain no markup or business logic; they call the widget registry.
- Target current WordPress APIs only (no deprecated functions), PHP 8.0+, strict types in `src/`, WPCS, PHPStan.
- Optimize by default: indexed queries, no N+1, no unbounded queries, conditional asset loading, versioned cache keys.
- Public files (`README.md`, this file, docs, changelog) describe shipped behavior only. Never include roadmap, plans, or TODOs.

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
- `bun install` — install JS dev tools
- `bun run build` — build blocks into `blocks/build/`
- `bun run lint:js` — JS lint

The pre-commit hook formats and lints staged PHP files. It blocks the commit if unfixable errors remain.

## Rules

- Escape output late, sanitize input early; capability + nonce on every write.
- Builder adapters contain no markup or business logic; they call the widget registry.
- Target current WordPress APIs only (no deprecated functions), PHP 8.0+, strict types in `src/`, WPCS, PHPStan.
- Optimize by default: indexed queries, no N+1, no unbounded queries, conditional asset loading, versioned cache keys.
- Public files (`README.md`, this file, docs, changelog) describe shipped behavior only. Never include roadmap, plans, or TODOs.

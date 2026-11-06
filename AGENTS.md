# Agent Instructions

## Project

- Single-site WordPress event manager plugin. Events will be a `re_event` custom post type with a REST API and a Bootstrap admin.
- Current scaffold: admin dashboard, activation, tooling, tests. The event model/API is not implemented; do not add frontend templates or builder integrations until requested.

## Commands

| Task | Command |
|---|---|
| Build assets | `bun run build` |
| Watch assets | `bun run watch` |
| Vite dev server | `bun run dev` |
| Typecheck | `bun check` |
| Format / check | `bun run format` / `bun run format:check` |
| PHP checks | `composer check` |
| Lint PHP | `composer lint` |
| Analyze PHP | `composer analyze` |
| Integration tests | `composer test` |
| Test one PHP file | `composer test -- tests/integration/admin-menu-test.php` |
| Frontend tests | `bun test` |

`composer check` runs Composer validate/normalize, PHPCS, PHPStan, and audit. Run `bun run build` after changing `assets/src/`, or leave `bun run watch` running.

## External References

| Need | File |
|---|---|
| Setup | `README.md` |
| Test database | `tests/README.md` |

## Architecture

- `re-events.php` bootstraps; `src/plugin.php` wires hooks; `src/activation.php` handles activation.
- Admin: `Admin\Menu` renders Plates templates through `Admin\View`. `src/admin/views/layouts/base.php` owns the host, declarative shadow template, and fallback boot script; pages live in `views/pages/`, partials in `views/components/`.
- Vite builds `assets/src/admin/index.ts` and `app.css` into `assets/build/` (ignored). Never edit `assets/build/`.
- `PLAN.md` is a local roadmap and must not be committed.

## Key conventions

- PHP 8.2+, `declare(strict_types=1)`, tabs.
- Lowercase-hyphenated files under `src/` and `assets/src/`; StudlyCase classes under `ReEvents\`; snake_case methods and array keys.
- Prefixes: `reevents`, `REEVENTS`, `ReEvents`, `re_events`; text domain `re-events`.
- Production uses declarative shadow DOM; dev uses template + JS mount for HMR. Production inlines CSS in the shadow root and fonts in `document.head`; PostCSS maps `:root`/`html`/`body` to `:host`. A light-DOM `#re-events-loading` shows until mount.
- Do not flatten Bootstrap 6 `@layer` blocks or add a CSS prefix; both are fine inside the shadow root.
- Admin views are Plates PHP templates: prepare data in `Menu`/services, escape with `esc_*`, call `$this->insert()` inline (it echoes), and keep queries/business logic out of views and REST controllers.
- Bootstrap is pinned to `6.0.0-alpha.1`. Do not upgrade blindly or use v5 class names.
- Icons: self-hosted Bootstrap Icons; use `<i class="bi bi-name"></i>` inside the shadow root.
- Drawer and Menu are initialized manually; keep overlay markup inside the shadow root.
- Use WordPress APIs; use `$wpdb->prepare()` with `%i` for identifiers. Escape output and verify nonces/capabilities on writes.

## Definition of done

- `composer check` passes.
- `composer test` passes when logic, queries, or schema change.
- `bun check` and `bun run format:check` pass when frontend files change.
- README and this file are updated when commands, architecture, or invariants change.
- No new runtime dependency without an explicit decision.

# Agent Instructions

## Project

- Single-site WordPress event manager plugin. Events will be a `re_event` custom post type with a REST API and a Tailwind CSS 4 + Flowbite admin.
- Current scaffold: admin dashboard, activation, tooling, tests. The event model/API is not implemented; do not add frontend templates or builder integrations until requested.

## Commands

| Task | Command |
|---|---|
| Build assets | `bun run build` |
| Watch assets | `bun run watch` |
| Vite dev server | `bun run dev` |
| Typecheck | `bun check` |
| Format / check | `bun run format` / `bun run format:check` |
| Sort Tailwind classes | `bun run format:tw` |
| Check Tailwind order | `bun run check:tw` |
| PHP checks | `composer check` |
| Lint PHP | `composer lint` |
| Analyze PHP | `composer analyze` |
| Integration tests | `composer test` |
| Test one PHP file | `composer test -- tests/integration/admin-menu-test.php` |
| Frontend tests | `bun test` |

`composer check` runs Composer validate/normalize, PHPCS, PHPStan, and audit. Run `bun run build` after changing `assets/src/`, or leave `bun run watch` running. Run `composer check:release` before packaging.

## External References

| Need | File |
|---|---|
| Setup | `README.md` |
| Test database | `tests/README.md` |

## Architecture

- `re-events.php` bootstraps; `src/plugin.php` wires hooks; `src/activation.php` handles activation.
- Admin: `Admin\Menu` renders Plates templates through `Admin\View`. Pages extend `layouts/base.php` (dashboard nav and content), which extends `layouts/root.php` (host, declarative shadow template, loader, fallback scripts). Pages live in `views/pages/`, partials in `views/components/`.
- Vite builds `assets/src/admin/index.ts` and `app.css` into `assets/build/` (ignored). Never edit `assets/build/`.
- `PLAN.md` is a local roadmap and must not be committed.

## Key conventions

- PHP 8.2+, `declare(strict_types=1)`, tabs.
- Lowercase-hyphenated files under `src/` and `assets/src/`; StudlyCase classes under `ReEvents\`; snake_case methods and array keys.
- Prefixes: `reevents`, `REEVENTS`, `ReEvents`, `re_events`; text domain `re-events`.
- Production uses declarative shadow DOM; dev uses template + JS mount for HMR. Production inlines CSS in the shadow root, injects fonts and Tailwind `@property` registrations into `document.head`; PostCSS maps `:root`/`html`/`body` to `:host`. A light-DOM `#re-events-loading` shows until mount.
- Do not flatten Tailwind `@layer` blocks or add a CSS prefix; both are fine inside the shadow root.
- Admin views are Plates PHP templates: prepare data in `Menu`/services, escape with `esc_*`, call `$this->insert()` inline (it echoes), and keep queries/business logic out of views and REST controllers.
- Admin screens require `manage_options`, use real WP submenu pages (`re-events-{view}`), and gate assets with `Menu::is_screen()`; do not add `?view=` nav links.
- Tailwind CSS 4 and Flowbite 4 are pinned in `package.json`. Do not add Bootstrap class names.
- PHP is formatted with Intelephense (PSR-12 spacing); `phpcs.xml.dist` skips the WordPress spacing sniffs that conflict with it.
- Icons: Lucide SVGs through `components/icon.php`, e.g. `$this->insert( 'components/icon', array( 'name' => 'search', 'icon_class' => 'w-4 h-4' ) )`. Add new SVGs under `views/components/icons/`; no icon font.
- Drawer and menu overlays are initialized manually; keep their markup inside the shadow root.
- Use WordPress APIs; use `$wpdb->prepare()` with `%i` for identifiers. Escape output and verify nonces/capabilities on writes.

## Definition of done

- `composer check` passes.
- `composer test` passes when logic, queries, or schema change.
- `bun check` and `bun run format:check` pass when frontend files change.
- `bun run check:tw` passes when admin templates change.
- README and this file are updated when commands, architecture, or invariants change.
- No new runtime dependency without an explicit decision.

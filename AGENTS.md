# Agent Instructions

## Project

- Single-site WordPress event manager plugin. Events will be a `re_event` custom post type with a REST API and a React 19 + shadcn/ui admin.
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

`composer check` runs Composer validate/normalize, PHPCS, PHPStan, and audit. Run `bun run build` after changing `assets/src/`, or leave `bun run watch` running. `bun run format` also sorts Tailwind classes in the TSX. Run `composer check:release` before packaging.

## External References

| Need | File |
|---|---|
| Setup | `README.md` |
| Test database | `tests/README.md` |

## Architecture

- `re-events.php` bootstraps; `src/plugin.php` wires hooks; `src/activation.php` handles activation.
- Admin: `Admin\Menu` renders the declarative shadow shell, loader, and JSON bootstrap data. `Admin\Assets` handles the bundle, shadow CSS, fonts, and Tailwind `@property` handoff.
- React lives in `assets/src/admin/`: `index.tsx` mounts, `app.tsx` owns the `HashRouter` routes, `components/ui/` holds shadcn components, `pages/` holds screens, `lib/` holds data and context.
- Vite builds `assets/src/admin/index.tsx` and `app.css` into `assets/build/` (ignored). Never edit `assets/build/`.
- `PLAN.md` is a local roadmap and must not be committed.

## Key conventions

- PHP 8.2+, `declare(strict_types=1)`, tabs.
- Lowercase-hyphenated files under `src/` and `assets/src/`; StudlyCase classes under `ReEvents\`; snake_case methods and array keys.
- Prefixes: `reevents`, `REEVENTS`, `ReEvents`, `re_events`; text domain `re-events`.
- Production uses declarative shadow DOM. The template carries the loader and React root; production inlines CSS there, dev lets Vite inject it. Fonts and Tailwind `@property` registrations go to `document.head`; PostCSS maps `:root`/`html`/`body` to `:host`. The app is revealed only after fonts are ready.
- Do not flatten Tailwind `@layer` blocks or add a CSS prefix; both are fine inside the shadow root.
- Bootstrap data comes from `Admin\Menu` as JSON; React reads it through `lib/bootstrap-context.tsx`. Do not query the database from React.
- Admin screens require `manage_options`, use real WP submenu pages (`re-events-{view}`), and gate assets with `Menu::is_screen()`; do not add `?view=` nav links.
- React 19, React Router 7, Tailwind CSS 4, and shadcn/ui are pinned in `package.json`. Do not add Bootstrap or Flowbite class names.
- PHP is formatted with Intelephense (PSR-12 spacing); `phpcs.xml.dist` skips the WordPress spacing sniffs that conflict with it.
- Icons: `lucide-react` inside components; no icon font or SVG partials.
- Keep the host and app at `calc(100svh - var(--wp-admin--admin-bar--height))` so the loader and rendered app share the same height.
- Use WordPress APIs; use `$wpdb->prepare()` with `%i` for identifiers. Escape output and verify nonces/capabilities on writes.

## Definition of done

- `composer check` passes.
- `composer test` passes when logic, queries, or schema change.
- `bun check` and `bun run format:check` pass when frontend files change.
- README and this file are updated when commands, architecture, or invariants change.
- No new runtime dependency without an explicit decision.

# ReEvents

Event management for WordPress with a REST API and a React + shadcn/ui admin.

![WordPress 6.8+](https://img.shields.io/badge/WordPress-6.8%2B-21759b)
![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%2B-777bb4)
![React 19](https://img.shields.io/badge/React-19-61DAFB)
![Tailwind CSS 4](https://img.shields.io/badge/Tailwind_CSS-4-06B6D4)
![Vite 8](https://img.shields.io/badge/Vite-8-646cff)

> [!WARNING]
> ReEvents is under active development. The event post type, REST API, and event screens are not implemented yet. The current release is a scaffold and is not production-ready.

## Requirements

| Requirement | Version |
|---|---|
| WordPress | 6.8+ |
| PHP | 8.2+ |
| Composer | Required for runtime views and tooling |
| Bun | 1.4+ when building assets |
| MySQL | Integration tests only |

## Installation

1. Copy the plugin to `wp-content/plugins/re-events`.
2. Run `composer install` in the plugin directory.
3. Run `bun install` and `bun run build`.
4. Activate **ReEvents** in the Plugins screen.

> [!IMPORTANT]
> `vendor/` and `assets/build/` are not committed. `composer install` and `bun run build` are required before the admin UI can render.
>
> Release packages must run `composer install --no-dev -o`, `bun install`, and `bun run build`, then include `vendor/` and `assets/build/`.

```bash
composer install --no-dev -o
bun install && bun run build
composer check:release
```

## Development

| Task | Command |
|---|---|
| Build assets | `bun run build` |
| Watch assets | `bun run watch` |
| Vite dev server | `bun run dev` |
| Typecheck | `bun check` |
| Format | `bun run format` |
| Format check | `bun run format:check` |
| PHP checks | `composer check` |
| Lint PHP | `composer lint` |
| Analyze PHP | `composer analyze` |
| Integration tests | `composer test` |
| Frontend tests | `bun test` |

`composer check` runs Composer validation, normalization, PHPCS, PHPStan, and audit. Run `bun run build` after changing `assets/src/`, or leave `bun run watch` running.

`bun run format` runs Bun's formatter over the admin bundle and sorts Tailwind classes in the TSX components. PHP is formatted by the Intelephense VS Code formatter; `phpcs.xml.dist` skips the WordPress spacing sniffs that conflict with its PSR-12 output.

### Hot reload

Define `REEVENTS_VITE_DEV` as `true` in `wp-config.php`, then run:

```bash
bun run dev
```

CSS and JavaScript update through Vite HMR. PHP changes trigger a full page reload.

> [!NOTE]
> Dev mode is HTTP-only. On an HTTPS site the built bundle is used instead. Remove the constant and run `bun run build` when you want the production bundle again.

## How it works

1. `Admin\Menu` builds the bootstrap data: current view, nav, events, and pagination.
2. `Admin\Menu::render()` prints a declarative shadow `<template>` containing the loader and React mount point, plus a JSON data script. Production inlines the built CSS in the template; dev lets Vite inject it.
3. `assets/src/admin/index.tsx` waits for fonts and styles, mounts the React app into the shadow root, then reveals it and removes the loader.
4. Nav links are the real WordPress submenu pages; React renders the page from `currentView`, so the admin menu, permissions, refresh, and bookmarks stay native.
5. A small inline boot script attaches the shadow root for browsers without declarative shadow DOM.
6. Fonts and Tailwind `@property` registrations live in `document.head` because Chromium ignores both inside a shadow root.

The production build writes `assets/build/`; it is generated and must not be edited. PostCSS runs Tailwind and maps `:root`, `html`, and `body` to `:host`. Tailwind's `@layer` blocks are kept as-is inside the shadow root.

## Project layout

```text
re-events.php                  Plugin header, constants, autoloader
src/
  plugin.php                   Hook wiring
  activation.php               Activation and deactivation
  admin/
    menu.php                   Admin menu, shell markup, bootstrap data
    assets.php                 Asset loading, shadow CSS, @property handoff
assets/src/admin/
  index.tsx                    Mount, dev injection, font/render timing
  app.tsx                      Router and page routes
  app.css                      Tailwind entry, shadcn tokens, host and loader styles
  components/
    app-layout.tsx             Header, nav links, page outlet
    ui/                        shadcn components (button, input, badge, table, card)
  lib/
    bootstrap-context.tsx      App-wide data provider
    data.ts                    Bootstrap types and JSON reader
    utils.ts                   cn() helper
  pages/
    events-page.tsx            Events table, search, pagination
    placeholder-page.tsx       Placeholder sections
components.json                 shadcn configuration
tests/
  integration/                 PHPUnit integration tests
  js/                          Frontend unit tests
```

## Testing

```bash
bun install && bun run build   # the asset tests read assets/build/
composer test                  # PHPUnit integration tests
bun test                       # Frontend unit tests
```

Integration tests need a disposable MySQL database. Configure `WP_TESTS_DB_*` and see `tests/README.md` for the setup.

## Notes

- React 19, Tailwind CSS 4, and shadcn/ui are pinned in `package.json`. Do not add Bootstrap or Flowbite class names.
- Single-site plugin. Multisite handling is out of scope unless requested.
- Keep bootstrap data in `Admin\Menu`; React reads it from the JSON script and never queries the database.
- The admin UI is isolated by Shadow DOM. Do not add a global CSS prefix or flatten Tailwind `@layer` blocks.
- Tailwind `@property` registrations must be emitted into `document.head`; Chromium ignores them inside a shadow root, which breaks borders, shadows, and rings.
- Keep the host at `calc(100svh - var(--wp-admin--admin-bar--height))`: the loader and app share that height, so fonts and CSS load without a layout shift.
- Prefixes: `reevents`, `REEVENTS`, `ReEvents`, `re_events`. Text domain: `re-events`.

# ReEvents

Event management for WordPress with a REST API and a Tailwind CSS 4 + Flowbite admin.

![WordPress 6.8+](https://img.shields.io/badge/WordPress-6.8%2B-21759b)
![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%2B-777bb4)
![Tailwind CSS 4](https://img.shields.io/badge/Tailwind_CSS-4-06B6D4)
![Flowbite 4](https://img.shields.io/badge/Flowbite-4-1C64F2)
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
| Sort Tailwind classes | `bun run format:tw` |
| Check Tailwind order | `bun run check:tw` |
| PHP checks | `composer check` |
| Lint PHP | `composer lint` |
| Analyze PHP | `composer analyze` |
| Integration tests | `composer test` |
| Frontend tests | `bun test` |

`composer check` runs Composer validation, normalization, PHPCS, PHPStan, and audit. Run `bun run build` after changing `assets/src/`, or leave `bun run watch` running.

PHP files are formatted by the Intelephense VS Code formatter, which handles HTML, PHP, JavaScript, and CSS in the same file. Because it follows PSR-12 spacing, `phpcs.xml.dist` does not enforce the WordPress paren-spacing and file-comment rules that conflict with it.

The `.githooks/pre-commit` hook sorts Tailwind classes in staged admin templates. Enable it once per clone with `git config core.hooksPath .githooks`.

### Hot reload

Define `REEVENTS_VITE_DEV` as `true` in `wp-config.php`, then run:

```bash
bun run dev
```

CSS and JavaScript update through Vite HMR. PHP changes trigger a full page reload.

> [!NOTE]
> Dev mode is HTTP-only. On an HTTPS site the built bundle is used instead. Remove the constant and run `bun run build` when you want the production bundle again.

## How it works

1. `Admin\Menu` builds the nav data for the current view.
2. `Admin\View` renders `src/admin/views/pages/*` through Plates.
3. `src/admin/views/layouts/root.php` outputs the host element and a declarative shadow `<template>` with the built CSS inlined. `layouts/base.php` adds the dashboard nav and content inside it.
4. `assets/src/admin/index.ts` mounts the shadow root in dev and fallback modes, wires the drawer and row menus, and injects dev CSS and fonts.
5. A small inline boot script handles browsers that ignore `shadowrootmode`.
6. Fonts and Tailwind `@property` registrations live in `document.head` because Chromium ignores both inside a shadow root.

The production build writes `assets/build/`; it is generated and must not be edited. PostCSS runs Tailwind and maps `:root`, `html`, and `body` to `:host`. Tailwind's `@layer` blocks are kept as-is inside the shadow root.

## Project layout

```text
re-events.php                  Plugin header, constants, autoloader
src/
  plugin.php                   Hook wiring
  activation.php               Activation and deactivation
  admin/
    menu.php                   Admin menu and view data
    assets.php                 Asset loading and shadow CSS handoff
    view.php                   Plates renderer
    views/
      layouts/root.php         Host, shadow template, loader, fallback scripts
      layouts/base.php         Dashboard nav and content
      components/nav.php       Navigation and drawer
      components/icon.php      Lucide icon renderer
      components/icons/        Lucide SVG partials
      components/search.php    Search input
      components/badge.php     Status badge
      components/button.php    Button variants and sizes
      components/pagination.php Pagination
      pages/events.php         Events page
      pages/placeholder.php    Placeholder section
assets/src/admin/
  index.ts                     Shadow mount, dev injection, components
  app.css                      Tailwind, Flowbite, host styles, theme
tests/
  integration/                 PHPUnit integration tests
  js/                          Frontend unit tests
```

## Testing

```bash
composer test    # PHPUnit integration tests
bun test         # Frontend unit tests
```

Integration tests need a disposable MySQL database. Configure `WP_TESTS_DB_*` and see `tests/README.md` for the setup.

## Notes

- Tailwind CSS 4 and Flowbite 4 are pinned in `package.json`. Do not add Bootstrap class names.
- Single-site plugin. Multisite handling is out of scope unless requested.
- Admin views are Plates PHP templates. Keep data preparation in `Menu`/services and business logic out of templates.
- The admin UI is isolated by Shadow DOM. Do not add a global CSS prefix or flatten Tailwind `@layer` blocks.
- Tailwind `@property` registrations must be emitted into `document.head`; Chromium ignores them inside a shadow root, which breaks borders, shadows, and rings.
- Prefixes: `reevents`, `REEVENTS`, `ReEvents`, `re_events`. Text domain: `re-events`.

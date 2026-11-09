# Contributing

ReEvents is GPL-2.0-or-later. Contributions are welcome under the same license.

## Setup

```bash
composer install
bun install
bun run wp-env start
```

## Checks

Run these before opening a pull request:

```bash
composer format
composer lint
composer analyze
composer test
bun run lint:js
bun run build
bun run docs:build
bun run wp-env run cli wp re-events self-test
```

If you changed queries or booking paths, also run:

```bash
bun run wp-env run cli wp re-events bench seed
bun run wp-env run cli wp re-events bench run --assert
bun run wp-env run cli wp re-events bench clean
```

## Standards

- PHP 8.0+, `declare( strict_types=1 );` in `src/`.
- WordPress Coding Standards, tabs, kebab-case file names.
- Namespace `ReEvents\` maps to `src/`.
- Prefix database identifiers with `reevents_`, PHP globals with `re_events_` or `RE_EVENTS_`, and meta keys with `_reevents_`.
- Escape output late, sanitize input early, and check capability plus nonce on every write.
- Use current WordPress APIs only.
- Keep builder adapters free of markup and business logic.
- Paginate and index list queries. Do not introduce unbounded queries or N+1 patterns.
- Public files describe shipped behavior only. Do not add roadmap, plans, or TODOs.

## Commits

Use Conventional Commits:

```
feat(admin): add attendee field repeater
fix(api): require auth when the API is disabled
docs: document API keys
```

Keep the subject imperative and under 72 characters. Add a body only for non-obvious context.

## Pull requests

- One focused change per pull request.
- Include the behavior change and any migration notes.
- Add or update tests and docs when behavior changes.
- Confirm the checks above pass.

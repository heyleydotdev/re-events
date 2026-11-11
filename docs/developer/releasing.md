# Releasing

The release gate: `1.0.0` is not tagged until the trademark policy is in place and the zip installs on a clean WordPress.

## 1. Version

Bump the plugin header and `RE_EVENTS_VERSION` in `re-events.php`, the version in `readme.txt` (`Stable tag`), and the changelog entry.

## 2. Checks

```bash
composer install
composer lint
composer analyze
composer test
composer test:integration
bun install
bun run lint:js
bun run build
```

Regenerate everything that is committed:

```bash
composer api-docs
bun run wp-env run cli wp re-events shortcode-docs | sed -n '/^# ReEvents shortcodes/,$p' > docs/shortcodes/README.md
bun run wp-env run cli wp re-events openapi > docs/api/openapi.json
```

Then run the runtime checks:

```bash
bun run wp-env run cli wp re-events self-test
bun run wp-env run cli wp re-events bench seed
bun run wp-env run cli wp re-events bench run --assert
bun run wp-env run cli wp re-events bench clean
```

## 3. Zip

```bash
composer release
```

`bin/build-release.sh` installs production dependencies, builds the blocks and writes `re-events.zip` from `.distignore`. Confirm the zip contains `vendor/rlanvin/php-rrule`, `blocks/build/`, `languages/re-events.pot`, `readme.txt` and does not contain `tests/`, `node_modules/`, `PLAN.md` or `stubs/`.

Install the zip on a clean WordPress with `WP_DEBUG` on. Create an event, a ticket, a free booking and a paid test booking before tagging.

## 4. Tag

```bash
git tag 1.0.0
git push origin main --tags
```

Upload the zip and wp.org assets (banner, icon, screenshots) for the submission review.

#!/usr/bin/env bash
# Build the release zip. Never run this with uncommitted work you need.
set -euo pipefail

cd "$(dirname "$0")/.."

STAGE="$(mktemp -d)"
ZIP="$PWD/re-events.zip"

restore_dev_dependencies() {
	composer install --no-interaction >/dev/null 2>&1 || true
}
trap 'rm -rf "$STAGE"; restore_dev_dependencies' EXIT

composer install --no-dev --optimize-autoloader --no-interaction
bun install --frozen-lockfile
bun run build

mkdir -p "$STAGE/re-events"
rsync -a --exclude-from=.distignore ./ "$STAGE/re-events/"
rm -f "$ZIP"
( cd "$STAGE" && zip -qr "$ZIP" re-events )

test -f "$STAGE/re-events/vendor/rlanvin/php-rrule/composer.json"
test -f "$STAGE/re-events/blocks/build/map/index.js"
test -f "$STAGE/re-events/src/cli/import-export.php"
test ! -e "$STAGE/re-events/.gitattributes"
test ! -e "$STAGE/re-events/.phpunit.result.cache"
test ! -e "$STAGE/re-events/.phpstan-cache"
test ! -e "$STAGE/re-events/bin"
echo "Built $ZIP"

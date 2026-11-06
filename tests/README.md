# Integration tests

RE Events uses the WordPress PHPUnit library from `wp-phpunit/wp-phpunit` and a
dedicated test database. The WordPress test suite drops and recreates tables in
that database, so never point it at a site database you care about.

## Setup

1. Install dependencies:

   ```bash
   composer install
   ```

2. Create an empty test database. For example, with Local's MySQL:

   ```bash
   mysql -h 127.0.0.1 -P 10004 -u root -p \
     -e "CREATE DATABASE IF NOT EXISTS reevents_tests CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   ```

3. Point the test suite at that database. The defaults are `reevents_tests`,
   `root`, an empty password, and `localhost`. Override them with:

   - `WP_TESTS_DB_NAME`
   - `WP_TESTS_DB_USER`
   - `WP_TESTS_DB_PASSWORD`
   - `WP_TESTS_DB_HOST` (for example `127.0.0.1:10004`)
   - `WP_CORE_DIR` (optional; defaults to the WordPress installation four
     directories above this plugin)
   - `WP_TESTS_DIR` (optional; defaults to `vendor/wp-phpunit/wp-phpunit`)

   Git Bash example:

   ```bash
   WP_TESTS_DB_NAME=reevents_tests \
   WP_TESTS_DB_USER=root \
   WP_TESTS_DB_PASSWORD=root \
   WP_TESTS_DB_HOST=127.0.0.1:10004 \
   composer test
   ```

   PowerShell example:

   ```powershell
   $env:WP_TESTS_DB_NAME = "reevents_tests"
   $env:WP_TESTS_DB_USER = "root"
   $env:WP_TESTS_DB_PASSWORD = "root"
   $env:WP_TESTS_DB_HOST = "127.0.0.1:10004"
   composer test
   ```

## Coverage

The scaffold suite verifies that the plugin boots. Add tests per milestone for
dates, saves, queries, REST permissions, templates, and registrations.

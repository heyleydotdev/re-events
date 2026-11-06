<?php
/**
 * WordPress test suite configuration.
 *
 * This database is dropped and recreated by the WordPress test suite. Never
 * point these values at a site database you care about.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
// phpcs:disable Universal.Operators.DisallowShortTernary.Found
// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited

$reevents_core_dir = getenv( 'WP_CORE_DIR' );

if ( false === $reevents_core_dir || '' === $reevents_core_dir ) {
	$reevents_core_dir = dirname( __DIR__, 4 );
}

define( 'ABSPATH', rtrim( $reevents_core_dir, '/\\' ) . '/' );

define( 'WP_DEBUG', true );

define( 'DB_NAME', getenv( 'WP_TESTS_DB_NAME' ) ?: 'reevents_tests' );
define( 'DB_USER', getenv( 'WP_TESTS_DB_USER' ) ?: 'root' );
define( 'DB_PASSWORD', getenv( 'WP_TESTS_DB_PASSWORD' ) ?: '' );
define( 'DB_HOST', getenv( 'WP_TESTS_DB_HOST' ) ?: 'localhost' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

define( 'AUTH_KEY', 'reevents-tests-auth-key' );
define( 'SECURE_AUTH_KEY', 'reevents-tests-secure-auth-key' );
define( 'LOGGED_IN_KEY', 'reevents-tests-logged-in-key' );
define( 'NONCE_KEY', 'reevents-tests-nonce-key' );
define( 'AUTH_SALT', 'reevents-tests-auth-salt' );
define( 'SECURE_AUTH_SALT', 'reevents-tests-secure-auth-salt' );
define( 'LOGGED_IN_SALT', 'reevents-tests-logged-in-salt' );
define( 'NONCE_SALT', 'reevents-tests-nonce-salt' );

$table_prefix = 'wptests_';

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'RE Events Test Blog' );
define( 'WP_PHP_BINARY', PHP_BINARY );
define( 'WPLANG', '' );

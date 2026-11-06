<?php
/**
 * Integration test bootstrap.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound

$reevents_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( false === $reevents_tests_dir || '' === $reevents_tests_dir ) {
	$reevents_tests_dir = dirname( __DIR__ ) . '/vendor/wp-phpunit/wp-phpunit';
}

$reevents_autoloader = dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! is_readable( $reevents_autoloader ) ) {
	throw new RuntimeException( 'Composer dependencies are missing. Run composer install.' );
}

if ( ! is_dir( $reevents_tests_dir ) ) {
	throw new RuntimeException( 'WordPress PHPUnit library not found. Run composer install.' );
}

require_once $reevents_autoloader;

define( 'WP_TESTS_CONFIG_FILE_PATH', __DIR__ . '/wp-tests-config.php' );

require_once $reevents_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		require dirname( __DIR__ ) . '/re-events.php';
	}
);

require $reevents_tests_dir . '/includes/bootstrap.php';

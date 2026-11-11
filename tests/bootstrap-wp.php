<?php
/**
 * WordPress integration test bootstrap.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

define( 'RE_EVENTS_TESTING', true );

$reevents_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $reevents_tests_dir ) {
	$reevents_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

$reevents_polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );

if ( false !== $reevents_polyfills_path ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- WordPress test suite constant.
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $reevents_polyfills_path );
}

if ( ! file_exists( "{$reevents_tests_dir}/includes/functions.php" ) ) {
	echo "Could not find {$reevents_tests_dir}/includes/functions.php, run bin/install-wp-tests.sh first." . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

require_once "{$reevents_tests_dir}/includes/functions.php";

/**
 * Load the plugin under test.
 */
function reevents_manually_load_plugin(): void {
	require dirname( __DIR__ ) . '/re-events.php';
}

tests_add_filter( 'muplugins_loaded', 'reevents_manually_load_plugin' );

/**
 * Create and empty the plugin tables.
 *
 * Custom tables survive the core table reset, so old rows would leak
 * between runs and collide with reused post IDs.
 */
function reevents_reset_tables(): void {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Test reset.
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'reevents_' ) . '%' ) );

	\ReEvents\Data\Schema::install();

	foreach ( [ 'reevents_occurrences', 'reevents_tickets', 'reevents_bookings', 'reevents_attendees' ] as $reevents_table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Test reset.
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}{$reevents_table}" );
	}
}

tests_add_filter( 'muplugins_loaded', 'reevents_reset_tables' );

require "{$reevents_tests_dir}/includes/bootstrap.php";

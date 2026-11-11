<?php
/**
 * Rate limit integration tests.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Tests\Integration;

use ReEvents\Services\Rate_Limit;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Covers the per-IP limiter and its booking endpoint wiring.
 */
final class Rate_Limit_Test extends WP_UnitTestCase {

	/**
	 * Give the limiter a stable remote address.
	 */
	public function set_up(): void {
		parent::set_up();

		$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
	}

	/**
	 * Remove the limiter state.
	 */
	public function tear_down(): void {
		foreach ( [ 'limit', 'booking' ] as $bucket ) {
			$key = 'reevents_rate_' . md5( $bucket . '|203.0.113.10' );
			delete_transient( $key );
			delete_option( $key );
			delete_option( $key . '_expires' );
		}

		unset( $_SERVER['REMOTE_ADDR'] );

		parent::tear_down();
	}

	/**
	 * The limiter blocks attempts after the maximum.
	 */
	public function test_blocks_after_max_attempts(): void {
		self::assertFalse( Rate_Limit::hit( 'limit', 2 ) );
		self::assertFalse( Rate_Limit::hit( 'limit', 2 ) );
		self::assertTrue( Rate_Limit::hit( 'limit', 2 ) );
	}

	/**
	 * An expired window starts a fresh count.
	 */
	public function test_expired_window_resets(): void {
		$key = 'reevents_rate_' . md5( 'limit|203.0.113.10' );

		update_option( $key, 5, false );
		update_option( $key . '_expires', time() - 1, false );

		self::assertFalse( Rate_Limit::hit( 'limit', 2 ) );

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Test counter value.
		self::assertSame( '1', $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $key ) ) );
	}

	/**
	 * Cleanup removes expired counters and keeps live ones.
	 */
	public function test_cleanup_removes_expired_counters(): void {
		$expired = 'reevents_rate_' . md5( 'limit|203.0.113.10' );
		$live    = 'reevents_rate_' . md5( 'booking|203.0.113.10' );

		update_option( $expired, 1, false );
		update_option( $expired . '_expires', time() - 60, false );
		update_option( $live, 1, false );
		update_option( $live . '_expires', time() + 60, false );

		Rate_Limit::cleanup();

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup check.
		$found = $wpdb->get_col(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table name.
				"SELECT option_name FROM {$wpdb->options} WHERE option_name IN (%s, %s, %s, %s)",
				$expired,
				$expired . '_expires',
				$live,
				$live . '_expires'
			)
		);

		self::assertEqualsCanonicalizing( [ $live, $live . '_expires' ], $found );
	}

	/**
	 * The booking endpoint returns 429 after five attempts.
	 */
	public function test_booking_endpoint_rate_limits(): void {
		$request = new WP_REST_Request( 'POST', '/re-events/v1/events/1/bookings' );
		$request->set_body_params(
			[
				'ticket_id' => 1,
				'quantity'  => 1,
			]
		);

		for ( $i = 0; $i < 5; $i++ ) {
			rest_do_request( $request );
		}

		$response = rest_do_request( $request );

		self::assertSame( 429, $response->get_status() );
	}
}

<?php
/**
 * REST and API key security tests.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Tests\Integration;

use ReEvents\Services\API_Keys;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Covers public access, API keys and disabled API behavior.
 */
final class REST_Security_Test extends WP_UnitTestCase {

	/**
	 * Simulate a plugin REST request for API key authentication.
	 */
	public function set_up(): void {
		parent::set_up();

		$GLOBALS['wp']->query_vars['rest_route'] = '/re-events/v1/events';
	}

	/**
	 * Remove headers and restore settings between tests.
	 */
	public function tear_down(): void {
		unset( $_SERVER['HTTP_AUTHORIZATION'], $_SERVER['HTTP_X_REEVENTS_KEY'] );

		parent::tear_down();
	}

	/**
	 * Booking lists require a capability.
	 */
	public function test_booking_list_requires_capability(): void {
		wp_set_current_user( 0 );

		$request  = new WP_REST_Request( 'GET', '/re-events/v1/events/1/bookings' );
		$response = rest_do_request( $request );

		self::assertContains( $response->get_status(), [ 401, 403 ] );
	}

	/**
	 * Invalid API keys do not authenticate.
	 */
	public function test_invalid_api_key_does_not_authenticate(): void {
		wp_set_current_user( 0 );
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer re_invalid';

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook.
		self::assertSame( 0, (int) apply_filters( 'determine_current_user', 0 ) );
	}

	/**
	 * Valid API keys authenticate as their owner.
	 */
	public function test_api_key_authenticates(): void {
		wp_set_current_user( 0 );
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$key     = API_Keys::create( 'Integration test', $user_id );

		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $key;

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook.
		self::assertSame( $user_id, (int) apply_filters( 'determine_current_user', 0 ) );

		$keys = API_Keys::all();
		API_Keys::revoke( (string) array_key_first( $keys ) );
	}

	/**
	 * API keys are ignored outside the plugin REST namespace.
	 */
	public function test_api_key_is_ignored_outside_plugin_rest(): void {
		wp_set_current_user( 0 );

		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$key     = API_Keys::create( 'Scope test', $user_id );

		$_SERVER['HTTP_AUTHORIZATION']           = 'Bearer ' . $key;
		$GLOBALS['wp']->query_vars['rest_route'] = '/wp/v2/posts';

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook.
		self::assertSame( 0, (int) apply_filters( 'determine_current_user', 0 ) );

		$keys = API_Keys::all();
		API_Keys::revoke( (string) array_key_first( $keys ) );
	}

	/**
	 * Disabling the API blocks unauthenticated requests.
	 */
	public function test_disabled_api_blocks_public_requests(): void {
		$settings                = get_option( 'reevents_settings', [] );
		$settings                = is_array( $settings ) ? $settings : [];
		$settings['api_enabled'] = false;
		update_option( 'reevents_settings', $settings );

		wp_set_current_user( 0 );

		$request  = new WP_REST_Request( 'GET', '/re-events/v1/events' );
		$response = rest_do_request( $request );

		self::assertSame( 404, $response->get_status() );

		$settings['api_enabled'] = true;
		update_option( 'reevents_settings', $settings );
	}

	/**
	 * Bad nonces are rejected.
	 */
	public function test_bad_nonce_is_rejected(): void {
		self::assertFalse( wp_verify_nonce( 'invalid', 'reevents_create_api_key' ) );
	}
}

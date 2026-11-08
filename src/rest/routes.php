<?php
/**
 * REST routes.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\REST;

use WP_REST_Response;

/**
 * Registers the ReEvents REST routes.
 */
final class Routes {

	/**
	 * Hook into WordPress.
	 */
	public static function register(): void {
		add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
	}

	/**
	 * Register routes.
	 */
	public static function register_routes(): void {
		register_rest_route(
			're-events/v1',
			'/ping',
			[
				'methods'             => 'GET',
				'callback'            => [ self::class, 'ping' ],
				'permission_callback' => '__return_true',
			]
		);

		$events = new Events_Controller();
		$events->register_routes();
	}

	/**
	 * Health check.
	 */
	public static function ping(): WP_REST_Response {
		return rest_ensure_response(
			[
				'status'  => 'ok',
				'version' => RE_EVENTS_VERSION,
			]
		);
	}
}

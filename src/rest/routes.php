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
				[
					'methods'             => 'GET',
					'callback'            => [ self::class, 'ping' ],
					'permission_callback' => '__return_true',
				],
				'schema'   => [ self::class, 'ping_schema' ],
				'response' => 'item',
			]
		);

		$events = new Events_Controller();
		$events->register_routes();

		$venues = new Venues_Controller();
		$venues->register_routes();

		$organizers = new Organizers_Controller();
		$organizers->register_routes();

		$categories = new Categories_Controller();
		$categories->register_routes();
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

	/**
	 * Health check schema.
	 *
	 * @return array<string, mixed>
	 */
	public static function ping_schema(): array {
		return [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'health',
			'type'       => 'object',
			'properties' => [
				'status'  => [ 'type' => 'string' ],
				'version' => [ 'type' => 'string' ],
			],
		];
	}
}

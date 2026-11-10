<?php
/**
 * REST routes.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\REST;

use ReEvents\Admin\Settings;
use ReEvents\Services\API_Keys;
use ReEvents\Services\Payment_Service;
use ReEvents\Services\Rate_Limit;
use WP_Error;
use WP_REST_Request;
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
		add_filter( 'rest_pre_dispatch', [ self::class, 'guard' ], 10, 3 );
		add_filter( 'rest_pre_serve_request', [ self::class, 'cors' ], 10, 3 );
	}

	/**
	 * Apply public access, rate limits and the default page size.
	 *
	 * @param mixed           $result  Dispatch result.
	 * @param mixed           $server  REST server.
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public static function guard( $result, $server, $request ) {
		if ( is_wp_error( $result ) || ! str_starts_with( $request->get_route(), '/re-events/v1' ) ) {
			return $result;
		}

		if ( str_starts_with( $request->get_route(), '/re-events/v1/webhooks/' ) ) {
			return $result;
		}

		if ( ! Settings::get( 'api_enabled', true ) && ( ! is_user_logged_in() || API_Keys::request_uses_auth_header() ) ) {
			return new WP_Error( 'rest_disabled', __( 'The API is disabled.', 're-events' ), [ 'status' => 404 ] );
		}

		if ( 'OPTIONS' === $request->get_method() ) {
			return $result;
		}

		self::set_per_page( $request );

		if ( is_user_logged_in() ) {
			return $result;
		}

		if ( ! Settings::get( 'api_public', true ) ) {
			return new WP_Error( 'rest_forbidden', __( 'Authentication required.', 're-events' ), [ 'status' => 401 ] );
		}

		$limit = (int) Settings::get( 'api_rate_limit', 0 );

		if ( $limit > 0 && Rate_Limit::hit( 'rest', $limit, MINUTE_IN_SECONDS ) ) {
			return new WP_Error( 'rest_rate_limited', __( 'Too many requests. Try again later.', 're-events' ), [ 'status' => 429 ] );
		}

		return $result;
	}

	/**
	 * Apply the default page size when the request does not set one.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	private static function set_per_page( WP_REST_Request $request ): void {
		$query = $request->get_query_params();
		$body  = $request->get_body_params();

		if ( isset( $query['per_page'] ) || isset( $body['per_page'] ) ) {
			return;
		}

		$request->set_param( 'per_page', max( 1, min( 100, (int) Settings::get( 'api_per_page', 10 ) ) ) );
	}

	/**
	 * Send CORS headers for allowed origins.
	 *
	 * @param mixed           $served  Whether the request was served.
	 * @param mixed           $result  Response.
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public static function cors( $served, $result, $request ) {
		if ( ! str_starts_with( $request->get_route(), '/re-events/v1' ) ) {
			return $served;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Origin header.
		$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? untrailingslashit( esc_url_raw( wp_unslash( (string) $_SERVER['HTTP_ORIGIN'] ) ) ) : '';

		if ( '' === $origin ) {
			return $served;
		}

		$allowed = array_filter( array_map( 'trim', explode( "\n", (string) Settings::get( 'api_cors', '' ) ) ) );

		if ( ! in_array( $origin, $allowed, true ) ) {
			return $served;
		}

		header( 'Access-Control-Allow-Origin: ' . $origin );
		header( 'Vary: Origin' );
		header( 'Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce, X-ReEvents-Key' );
		header( 'Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS' );

		return $served;
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

		$settings = new Settings_Controller();
		$settings->register_routes();

		$tickets = new Tickets_Controller();
		$tickets->register_routes();

		$bookings = new Bookings_Controller();
		$bookings->register_routes();

		$booking_form = new Booking_Form_Controller();
		$booking_form->register_routes();

		register_rest_route(
			're-events/v1',
			'/webhooks/(?P<gateway>[a-z0-9_-]+)',
			[
				'methods'             => 'POST',
				'callback'            => [ self::class, 'webhook' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	/**
	 * Handle a gateway webhook.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function webhook( WP_REST_Request $request ) {
		$result = Payment_Service::handle_webhook(
			sanitize_key( (string) $request->get_param( 'gateway' ) ),
			(string) $request->get_body(),
			$request->get_headers()
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( [ 'received' => true ], 200 );
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

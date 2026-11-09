<?php
/**
 * Settings REST controller.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\REST;

use ReEvents\Admin\Settings;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Read-only settings endpoint.
 */
final class Settings_Controller extends WP_REST_Controller {

	/**
	 * Namespace.
	 *
	 * @var string
	 */
	protected $namespace = 're-events/v1';

	/**
	 * REST base.
	 *
	 * @var string
	 */
	protected $rest_base = 'settings';

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_item' ],
					'permission_callback' => [ $this, 'get_item_permissions_check' ],
				],
				'schema'   => [ $this, 'get_public_item_schema' ],
				'response' => 'item',
			]
		);
	}

	/**
	 * Check whether the user may read settings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		if ( current_user_can( 'reevents_manage_settings' ) ) {
			return true;
		}

		return new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to read settings.', 're-events' ), [ 'status' => rest_authorization_required_code() ] );
	}

	/**
	 * Get settings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_item( $request ) {
		return rest_ensure_response(
			[
				'timezone'     => (string) Settings::get( 'timezone', 'UTC' ),
				'time_display' => (string) Settings::get( 'time_display', 'event' ),
				'date_format'  => (string) Settings::get( 'date_format', get_option( 'date_format' ) ),
				'time_format'  => (string) Settings::get( 'time_format', get_option( 'time_format' ) ),
			]
		);
	}

	/**
	 * Item schema.
	 *
	 * @return array<string, mixed>
	 */
	public function get_item_schema() {
		return [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'settings',
			'type'       => 'object',
			'properties' => [
				'timezone'     => [
					'description' => __( 'Default event timezone.', 're-events' ),
					'type'        => 'string',
				],
				'time_display' => [
					'description' => __( 'Time display mode.', 're-events' ),
					'type'        => 'string',
					'enum'        => [ 'site', 'event', 'visitor' ],
				],
				'date_format'  => [
					'description' => __( 'Date format.', 're-events' ),
					'type'        => 'string',
				],
				'time_format'  => [
					'description' => __( 'Time format.', 're-events' ),
					'type'        => 'string',
				],
			],
		];
	}
}

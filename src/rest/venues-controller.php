<?php
/**
 * Venues REST controller.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\REST;

use ReEvents\Data\Meta;
use ReEvents\Data\Venue_Repository;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Venues endpoints.
 */
final class Venues_Controller extends WP_REST_Controller {

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
	protected $rest_base = 'venues';

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
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => '__return_true',
					'args'                => $this->get_collection_params(),
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_item' ],
					'permission_callback' => [ $this, 'create_item_permissions_check' ],
					'args'                => $this->get_write_args( true ),
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_item' ],
					'permission_callback' => [ $this, 'get_item_permissions_check' ],
					'args'                => $this->get_id_arg(),
				],
				[
					'methods'             => 'PATCH',
					'callback'            => [ $this, 'update_item' ],
					'permission_callback' => [ $this, 'update_item_permissions_check' ],
					'args'                => array_merge( $this->get_id_arg(), $this->get_write_args( false ) ),
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_item' ],
					'permission_callback' => [ $this, 'delete_item_permissions_check' ],
					'args'                => $this->get_id_arg(),
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);
	}

	/**
	 * Public item access, or edit access for unpublished venues.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		$post = get_post( (int) $request['id'] );

		if ( ! $post || 'reevents_venue' !== $post->post_type ) {
			return new WP_Error( 'rest_venue_invalid_id', __( 'Invalid venue ID.', 're-events' ), [ 'status' => 404 ] );
		}

		if ( 'publish' === $post->post_status || current_user_can( 'edit_post', $post->ID ) ) {
			return true;
		}

		return new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to view this venue.', 're-events' ), [ 'status' => rest_authorization_required_code() ] );
	}

	/**
	 * Check whether the user may create venues.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		if ( current_user_can( 'publish_reevents_venues' ) ) {
			return true;
		}

		return new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to create venues.', 're-events' ), [ 'status' => rest_authorization_required_code() ] );
	}

	/**
	 * Check whether the user may edit the venue.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		if ( current_user_can( 'edit_post', (int) $request['id'] ) ) {
			return true;
		}

		return new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to edit this venue.', 're-events' ), [ 'status' => rest_authorization_required_code() ] );
	}

	/**
	 * Check whether the user may delete the venue.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function delete_item_permissions_check( $request ) {
		if ( current_user_can( 'delete_post', (int) $request['id'] ) ) {
			return true;
		}

		return new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to delete this venue.', 're-events' ), [ 'status' => rest_authorization_required_code() ] );
	}

	/**
	 * List venues.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$result = ( new Venue_Repository() )->query(
			[
				'per_page' => min( 100, max( 1, (int) $request['per_page'] ) ),
				'page'     => max( 1, (int) $request['page'] ),
				'search'   => (string) $request['search'],
			]
		);
		$items  = [];

		foreach ( $result['items'] as $venue ) {
			$items[] = $this->prepare_item_for_response( $venue, $request )->get_data();
		}

		$response = rest_ensure_response( $items );
		$response->header( 'X-WP-Total', (string) $result['total'] );
		$response->header( 'X-WP-TotalPages', (string) $result['pages'] );

		return $response;
	}

	/**
	 * Get a single venue.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$venue = ( new Venue_Repository() )->find( (int) $request['id'] );

		if ( ! $venue ) {
			return new WP_Error( 'rest_venue_invalid_id', __( 'Invalid venue ID.', 're-events' ), [ 'status' => 404 ] );
		}

		return $this->prepare_item_for_response( $venue, $request );
	}

	/**
	 * Create a venue.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$repo  = new Venue_Repository();
		$venue = $this->venue_from_request( $request );

		if ( '' === $venue['title'] ) {
			return new WP_Error( 'rest_venue_invalid_title', __( 'A venue title is required.', 're-events' ), [ 'status' => 400 ] );
		}

		$result = $repo->save( $venue );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$venue = $repo->find( (int) $result );

		if ( ! $venue ) {
			return new WP_Error( 'rest_venue_save_failed', __( 'The venue could not be saved.', 're-events' ), [ 'status' => 500 ] );
		}

		$response = $this->prepare_item_for_response( $venue, $request );
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( $this->namespace . '/' . $this->rest_base . '/' . $venue['id'] ) );

		return $response;
	}

	/**
	 * Update a venue.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$repo     = new Venue_Repository();
		$existing = $repo->find( (int) $request['id'] );

		if ( ! $existing ) {
			return new WP_Error( 'rest_venue_invalid_id', __( 'Invalid venue ID.', 're-events' ), [ 'status' => 404 ] );
		}

		$venue = $this->venue_from_request( $request, $existing );

		if ( '' === $venue['title'] ) {
			return new WP_Error( 'rest_venue_invalid_title', __( 'A venue title is required.', 're-events' ), [ 'status' => 400 ] );
		}

		$result = $repo->save( $venue );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$venue = $repo->find( (int) $request['id'] );

		if ( ! $venue ) {
			return new WP_Error( 'rest_venue_save_failed', __( 'The venue could not be saved.', 're-events' ), [ 'status' => 500 ] );
		}

		return $this->prepare_item_for_response( $venue, $request );
	}

	/**
	 * Delete a venue.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$repo  = new Venue_Repository();
		$venue = $repo->find( (int) $request['id'] );

		if ( ! $venue ) {
			return new WP_Error( 'rest_venue_invalid_id', __( 'Invalid venue ID.', 're-events' ), [ 'status' => 404 ] );
		}

		if ( ! $repo->delete( (int) $request['id'] ) ) {
			return new WP_Error( 'rest_venue_delete_failed', __( 'The venue could not be deleted.', 're-events' ), [ 'status' => 500 ] );
		}

		return rest_ensure_response(
			[
				'deleted'  => true,
				'previous' => $this->prepare_item_for_response( $venue, $request )->get_data(),
			]
		);
	}

	/**
	 * Build a venue from request parameters.
	 *
	 * @param WP_REST_Request      $request Request.
	 * @param array<string, mixed> $venue   Existing venue.
	 * @return array<string, mixed>
	 */
	private function venue_from_request( WP_REST_Request $request, array $venue = [] ): array {
		$venue = array_merge(
			[
				'id'          => 0,
				'title'       => '',
				'description' => '',
				'address'     => '',
				'lat'         => '',
				'lng'         => '',
				'website'     => '',
			],
			$venue
		);

		return [
			'id'          => (int) $venue['id'],
			'title'       => $request->has_param( 'title' ) ? sanitize_text_field( (string) $request['title'] ) : (string) $venue['title'],
			'description' => $request->has_param( 'description' ) ? sanitize_textarea_field( (string) $request['description'] ) : (string) $venue['description'],
			'address'     => $request->has_param( 'address' ) ? sanitize_textarea_field( (string) $request['address'] ) : (string) $venue['address'],
			'lat'         => $request->has_param( 'lat' ) ? Meta::sanitize_latitude( $request['lat'] ) : (string) $venue['lat'],
			'lng'         => $request->has_param( 'lng' ) ? Meta::sanitize_longitude( $request['lng'] ) : (string) $venue['lng'],
			'website'     => $request->has_param( 'website' ) ? esc_url_raw( (string) $request['website'] ) : (string) $venue['website'],
		];
	}

	/**
	 * Serialize a venue.
	 *
	 * @param array<string, mixed> $item    Venue.
	 * @param WP_REST_Request      $request Request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $item, $request ) {
		return rest_ensure_response( $item );
	}

	/**
	 * Collection parameters.
	 *
	 * @return array<string, mixed>
	 */
	public function get_collection_params() {
		$params = parent::get_collection_params();

		$params['search'] = [
			'description'       => __( 'Limit results to venues matching a search term.', 're-events' ),
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
		];

		return $params;
	}

	/**
	 * Writable venue parameters.
	 *
	 * @param bool $required Whether the title is required.
	 * @return array<string, mixed>
	 */
	private function get_write_args( bool $required ): array {
		return [
			'title'       => [
				'description'       => __( 'Venue title.', 're-events' ),
				'type'              => 'string',
				'required'          => $required,
				'sanitize_callback' => 'sanitize_text_field',
			],
			'description' => [
				'description'       => __( 'Venue description.', 're-events' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			],
			'address'     => [
				'description'       => __( 'Venue address.', 're-events' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			],
			'lat'         => [
				'description'       => __( 'Latitude.', 're-events' ),
				'type'              => 'string',
				'validate_callback' => [ self::class, 'validate_latitude' ],
				'sanitize_callback' => [ Meta::class, 'sanitize_latitude' ],
			],
			'lng'         => [
				'description'       => __( 'Longitude.', 're-events' ),
				'type'              => 'string',
				'validate_callback' => [ self::class, 'validate_longitude' ],
				'sanitize_callback' => [ Meta::class, 'sanitize_longitude' ],
			],
			'website'     => [
				'description'       => __( 'Venue website.', 're-events' ),
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
			],
		];
	}

	/**
	 * Validate a latitude.
	 *
	 * @param mixed $value Raw value.
	 * @return true|WP_Error
	 */
	public static function validate_latitude( $value ) {
		return '' === (string) $value || '' !== Meta::sanitize_latitude( $value )
			? true
			: new WP_Error( 'rest_venue_invalid_latitude', __( 'Latitude must be between -90 and 90.', 're-events' ) );
	}

	/**
	 * Validate a longitude.
	 *
	 * @param mixed $value Raw value.
	 * @return true|WP_Error
	 */
	public static function validate_longitude( $value ) {
		return '' === (string) $value || '' !== Meta::sanitize_longitude( $value )
			? true
			: new WP_Error( 'rest_venue_invalid_longitude', __( 'Longitude must be between -180 and 180.', 're-events' ) );
	}

	/**
	 * Item schema.
	 *
	 * @return array<string, mixed>
	 */
	public function get_item_schema() {
		return [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'venue',
			'type'       => 'object',
			'properties' => [
				'id'          => [
					'description' => __( 'Unique identifier for the venue.', 're-events' ),
					'type'        => 'integer',
					'readonly'    => true,
				],
				'title'       => [
					'description' => __( 'Venue title.', 're-events' ),
					'type'        => 'string',
				],
				'description' => [
					'description' => __( 'Venue description.', 're-events' ),
					'type'        => 'string',
				],
				'address'     => [
					'description' => __( 'Venue address.', 're-events' ),
					'type'        => 'string',
				],
				'lat'         => [
					'description' => __( 'Latitude.', 're-events' ),
					'type'        => 'string',
				],
				'lng'         => [
					'description' => __( 'Longitude.', 're-events' ),
					'type'        => 'string',
				],
				'website'     => [
					'description' => __( 'Venue website.', 're-events' ),
					'type'        => 'string',
					'format'      => 'uri',
				],
				'permalink'   => [
					'description' => __( 'Venue permalink.', 're-events' ),
					'type'        => 'string',
					'format'      => 'uri',
				],
			],
		];
	}

	/**
	 * Identifier argument.
	 *
	 * @return array<string, mixed>
	 */
	private function get_id_arg(): array {
		return [
			'id' => [
				'description'       => __( 'Unique identifier for the venue.', 're-events' ),
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			],
		];
	}
}

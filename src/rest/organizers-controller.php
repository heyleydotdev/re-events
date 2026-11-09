<?php
/**
 * Organizers REST controller.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\REST;

use ReEvents\Data\Organizer_Repository;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Organizers endpoints.
 */
final class Organizers_Controller extends WP_REST_Controller {

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
	protected $rest_base = 'organizers';

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
					'args'                => $this->get_write_args( false ),
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
	 * Public item access, or edit access for unpublished organizers.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		$post = get_post( (int) $request['id'] );

		if ( ! $post || 'reevents_organizer' !== $post->post_type ) {
			return new WP_Error( 'rest_organizer_invalid_id', __( 'Invalid organizer ID.', 're-events' ), [ 'status' => 404 ] );
		}

		if ( 'publish' === $post->post_status || current_user_can( 'edit_post', $post->ID ) ) {
			return true;
		}

		return new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to view this organizer.', 're-events' ), [ 'status' => rest_authorization_required_code() ] );
	}

	/**
	 * Check whether the user may create organizers.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		if ( current_user_can( 'publish_reevents_organizers' ) ) {
			return true;
		}

		return new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to create organizers.', 're-events' ), [ 'status' => rest_authorization_required_code() ] );
	}

	/**
	 * Check whether the user may edit the organizer.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		if ( current_user_can( 'edit_post', (int) $request['id'] ) ) {
			return true;
		}

		return new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to edit this organizer.', 're-events' ), [ 'status' => rest_authorization_required_code() ] );
	}

	/**
	 * Check whether the user may delete the organizer.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function delete_item_permissions_check( $request ) {
		if ( current_user_can( 'delete_post', (int) $request['id'] ) ) {
			return true;
		}

		return new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to delete this organizer.', 're-events' ), [ 'status' => rest_authorization_required_code() ] );
	}

	/**
	 * List organizers.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$result = ( new Organizer_Repository() )->query(
			[
				'per_page' => min( 100, max( 1, (int) $request['per_page'] ) ),
				'page'     => max( 1, (int) $request['page'] ),
				'search'   => (string) $request['search'],
			]
		);
		$items  = [];

		foreach ( $result['items'] as $organizer ) {
			$items[] = $this->prepare_item_for_response( $organizer, $request )->get_data();
		}

		$response = rest_ensure_response( $items );
		$response->header( 'X-WP-Total', (string) $result['total'] );
		$response->header( 'X-WP-TotalPages', (string) $result['pages'] );

		return $response;
	}

	/**
	 * Get a single organizer.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$organizer = ( new Organizer_Repository() )->find( (int) $request['id'] );

		if ( ! $organizer ) {
			return new WP_Error( 'rest_organizer_invalid_id', __( 'Invalid organizer ID.', 're-events' ), [ 'status' => 404 ] );
		}

		return $this->prepare_item_for_response( $organizer, $request );
	}

	/**
	 * Create an organizer.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$repo      = new Organizer_Repository();
		$organizer = $this->organizer_from_request( $request );

		if ( '' === $organizer['title'] ) {
			return new WP_Error( 'rest_organizer_invalid_title', __( 'An organizer title is required.', 're-events' ), [ 'status' => 400 ] );
		}

		$result = $repo->save( $organizer );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$organizer = $repo->find( (int) $result );

		if ( ! $organizer ) {
			return new WP_Error( 'rest_organizer_save_failed', __( 'The organizer could not be saved.', 're-events' ), [ 'status' => 500 ] );
		}

		$response = $this->prepare_item_for_response( $organizer, $request );
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( $this->namespace . '/' . $this->rest_base . '/' . $organizer['id'] ) );

		return $response;
	}

	/**
	 * Update an organizer.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$repo     = new Organizer_Repository();
		$existing = $repo->find( (int) $request['id'] );

		if ( ! $existing ) {
			return new WP_Error( 'rest_organizer_invalid_id', __( 'Invalid organizer ID.', 're-events' ), [ 'status' => 404 ] );
		}

		$organizer = $this->organizer_from_request( $request, $existing );

		if ( '' === $organizer['title'] ) {
			return new WP_Error( 'rest_organizer_invalid_title', __( 'An organizer title is required.', 're-events' ), [ 'status' => 400 ] );
		}

		$result = $repo->save( $organizer );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$organizer = $repo->find( (int) $request['id'] );

		if ( ! $organizer ) {
			return new WP_Error( 'rest_organizer_save_failed', __( 'The organizer could not be saved.', 're-events' ), [ 'status' => 500 ] );
		}

		return $this->prepare_item_for_response( $organizer, $request );
	}

	/**
	 * Delete an organizer.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$repo      = new Organizer_Repository();
		$organizer = $repo->find( (int) $request['id'] );

		if ( ! $organizer ) {
			return new WP_Error( 'rest_organizer_invalid_id', __( 'Invalid organizer ID.', 're-events' ), [ 'status' => 404 ] );
		}

		if ( ! $repo->delete( (int) $request['id'] ) ) {
			return new WP_Error( 'rest_organizer_delete_failed', __( 'The organizer could not be deleted.', 're-events' ), [ 'status' => 500 ] );
		}

		return rest_ensure_response(
			[
				'deleted'  => true,
				'previous' => $this->prepare_item_for_response( $organizer, $request )->get_data(),
			]
		);
	}

	/**
	 * Build an organizer from request parameters.
	 *
	 * @param WP_REST_Request      $request   Request.
	 * @param array<string, mixed> $organizer Existing organizer.
	 * @return array<string, mixed>
	 */
	private function organizer_from_request( WP_REST_Request $request, array $organizer = [] ): array {
		$organizer = array_merge(
			[
				'id'          => 0,
				'title'       => '',
				'description' => '',
			],
			$organizer
		);

		return [
			'id'          => (int) $organizer['id'],
			'title'       => $request->has_param( 'title' ) ? sanitize_text_field( (string) $request['title'] ) : (string) $organizer['title'],
			'description' => $request->has_param( 'description' ) ? sanitize_textarea_field( (string) $request['description'] ) : (string) $organizer['description'],
		];
	}

	/**
	 * Serialize an organizer.
	 *
	 * @param array<string, mixed> $item    Organizer.
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
			'description'       => __( 'Limit results to organizers matching a search term.', 're-events' ),
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
		];

		return $params;
	}

	/**
	 * Writable organizer parameters.
	 *
	 * @param bool $required Whether the title is required.
	 * @return array<string, mixed>
	 */
	private function get_write_args( bool $required ): array {
		return [
			'title'       => [
				'description'       => __( 'Organizer title.', 're-events' ),
				'type'              => 'string',
				'required'          => $required,
				'sanitize_callback' => 'sanitize_text_field',
			],
			'description' => [
				'description'       => __( 'Organizer description.', 're-events' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			],
		];
	}

	/**
	 * Item schema.
	 *
	 * @return array<string, mixed>
	 */
	public function get_item_schema() {
		return [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'organizer',
			'type'       => 'object',
			'properties' => [
				'id'          => [
					'description' => __( 'Unique identifier for the organizer.', 're-events' ),
					'type'        => 'integer',
					'readonly'    => true,
				],
				'title'       => [
					'description' => __( 'Organizer title.', 're-events' ),
					'type'        => 'string',
				],
				'description' => [
					'description' => __( 'Organizer description.', 're-events' ),
					'type'        => 'string',
				],
				'permalink'   => [
					'description' => __( 'Organizer permalink.', 're-events' ),
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
				'description'       => __( 'Unique identifier for the organizer.', 're-events' ),
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			],
		];
	}
}

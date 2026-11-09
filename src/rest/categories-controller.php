<?php
/**
 * Categories REST controller.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\REST;

use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Term;

/**
 * Event category endpoints.
 */
final class Categories_Controller extends WP_REST_Controller {

	/**
	 * Taxonomy.
	 */
	private const TAXONOMY = 'reevents_event_category';

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
	protected $rest_base = 'categories';

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
					'permission_callback' => [ $this, 'write_permissions_check' ],
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
					'permission_callback' => '__return_true',
					'args'                => $this->get_id_arg(),
				],
				[
					'methods'             => 'PATCH',
					'callback'            => [ $this, 'update_item' ],
					'permission_callback' => [ $this, 'write_permissions_check' ],
					'args'                => array_merge( $this->get_id_arg(), $this->get_write_args( false ) ),
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_item' ],
					'permission_callback' => [ $this, 'write_permissions_check' ],
					'args'                => $this->get_id_arg(),
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);
	}

	/**
	 * Check whether the user may manage categories.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function write_permissions_check( $request ) {
		if ( current_user_can( 'manage_categories' ) ) {
			return true;
		}

		return new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to manage categories.', 're-events' ), [ 'status' => rest_authorization_required_code() ] );
	}

	/**
	 * List categories.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$per_page = min( 100, max( 1, (int) $request['per_page'] ) );
		$page     = max( 1, (int) $request['page'] );
		$search   = (string) $request['search'];
		$args     = [
			'taxonomy'   => self::TAXONOMY,
			'hide_empty' => false,
			'number'     => $per_page,
			'offset'     => ( $page - 1 ) * $per_page,
			'orderby'    => 'name',
			'order'      => 'ASC',
		];

		if ( '' !== $search ) {
			$args['search'] = $search;
		}

		$terms = get_terms( $args );

		if ( is_wp_error( $terms ) ) {
			return $terms;
		}

		$count_args = [
			'taxonomy'   => self::TAXONOMY,
			'hide_empty' => false,
		];

		if ( '' !== $search ) {
			$count_args['search'] = $search;
		}

		$total = wp_count_terms( $count_args );

		if ( is_wp_error( $total ) ) {
			return $total;
		}

		$total = (int) $total;

		$items = [];

		foreach ( $terms as $term ) {
			$items[] = $this->prepare_item_for_response( $term, $request )->get_data();
		}

		$response = rest_ensure_response( $items );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) (int) ceil( $total / $per_page ) );

		return $response;
	}

	/**
	 * Get a single category.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$term = get_term( (int) $request['id'], self::TAXONOMY );

		if ( ! $term instanceof WP_Term ) {
			return new WP_Error( 'rest_category_invalid_id', __( 'Invalid category ID.', 're-events' ), [ 'status' => 404 ] );
		}

		return $this->prepare_item_for_response( $term, $request );
	}

	/**
	 * Create a category.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$result = wp_insert_term(
			sanitize_text_field( (string) $request['name'] ),
			self::TAXONOMY,
			[
				'description' => sanitize_textarea_field( (string) $request['description'] ),
				'parent'      => absint( $request['parent'] ),
			]
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$term = get_term( (int) $result['term_id'], self::TAXONOMY );

		if ( ! $term instanceof WP_Term ) {
			return new WP_Error( 'rest_category_save_failed', __( 'The category could not be saved.', 're-events' ), [ 'status' => 500 ] );
		}

		$response = $this->prepare_item_for_response( $term, $request );
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( $this->namespace . '/' . $this->rest_base . '/' . $term->term_id ) );

		return $response;
	}

	/**
	 * Update a category.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$term = get_term( (int) $request['id'], self::TAXONOMY );

		if ( ! $term instanceof WP_Term ) {
			return new WP_Error( 'rest_category_invalid_id', __( 'Invalid category ID.', 're-events' ), [ 'status' => 404 ] );
		}

		$fields = [];

		if ( $request->has_param( 'name' ) ) {
			$fields['name'] = sanitize_text_field( (string) $request['name'] );
		}

		if ( $request->has_param( 'description' ) ) {
			$fields['description'] = sanitize_textarea_field( (string) $request['description'] );
		}

		if ( $request->has_param( 'parent' ) ) {
			$fields['parent'] = absint( $request['parent'] );
		}

		$result = wp_update_term( $term->term_id, self::TAXONOMY, $fields );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$term = get_term( $term->term_id, self::TAXONOMY );

		if ( ! $term instanceof WP_Term ) {
			return new WP_Error( 'rest_category_save_failed', __( 'The category could not be saved.', 're-events' ), [ 'status' => 500 ] );
		}

		return $this->prepare_item_for_response( $term, $request );
	}

	/**
	 * Delete a category.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$term = get_term( (int) $request['id'], self::TAXONOMY );

		if ( ! $term instanceof WP_Term ) {
			return new WP_Error( 'rest_category_invalid_id', __( 'Invalid category ID.', 're-events' ), [ 'status' => 404 ] );
		}

		$previous = $this->prepare_item_for_response( $term, $request )->get_data();
		$result   = wp_delete_term( $term->term_id, self::TAXONOMY );

		if ( is_wp_error( $result ) || ! $result ) {
			return new WP_Error( 'rest_category_delete_failed', __( 'The category could not be deleted.', 're-events' ), [ 'status' => 500 ] );
		}

		return rest_ensure_response(
			[
				'deleted'  => true,
				'previous' => $previous,
			]
		);
	}

	/**
	 * Serialize a category.
	 *
	 * @param WP_Term         $item    Term.
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $item, $request ) {
		$link = get_term_link( $item );
		$data = [
			'id'          => $item->term_id,
			'name'        => $item->name,
			'slug'        => $item->slug,
			'description' => $item->description,
			'parent'      => $item->parent,
			'count'       => $item->count,
			'link'        => is_wp_error( $link ) ? '' : $link,
		];

		return rest_ensure_response( $data );
	}

	/**
	 * Collection parameters.
	 *
	 * @return array<string, mixed>
	 */
	public function get_collection_params() {
		$params = parent::get_collection_params();

		$params['search'] = [
			'description'       => __( 'Limit results to categories matching a search term.', 're-events' ),
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
		];

		return $params;
	}

	/**
	 * Writable category parameters.
	 *
	 * @param bool $required Whether the name is required.
	 * @return array<string, mixed>
	 */
	private function get_write_args( bool $required ): array {
		return [
			'name'        => [
				'description'       => __( 'Category name.', 're-events' ),
				'type'              => 'string',
				'required'          => $required,
				'sanitize_callback' => 'sanitize_text_field',
			],
			'description' => [
				'description'       => __( 'Category description.', 're-events' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			],
			'parent'      => [
				'description'       => __( 'Parent category term ID.', 're-events' ),
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
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
			'title'      => 'category',
			'type'       => 'object',
			'properties' => [
				'id'          => [
					'description' => __( 'Unique identifier for the category.', 're-events' ),
					'type'        => 'integer',
					'readonly'    => true,
				],
				'name'        => [
					'description' => __( 'Category name.', 're-events' ),
					'type'        => 'string',
				],
				'slug'        => [
					'description' => __( 'Category slug.', 're-events' ),
					'type'        => 'string',
					'readonly'    => true,
				],
				'description' => [
					'description' => __( 'Category description.', 're-events' ),
					'type'        => 'string',
				],
				'parent'      => [
					'description' => __( 'Parent category term ID.', 're-events' ),
					'type'        => 'integer',
				],
				'count'       => [
					'description' => __( 'Number of events in the category.', 're-events' ),
					'type'        => 'integer',
					'readonly'    => true,
				],
				'link'        => [
					'description' => __( 'Category archive link.', 're-events' ),
					'type'        => 'string',
					'format'      => 'uri',
					'readonly'    => true,
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
				'description'       => __( 'Unique identifier for the category.', 're-events' ),
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			],
		];
	}
}

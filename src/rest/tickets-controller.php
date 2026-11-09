<?php
/**
 * Tickets REST controller.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\REST;

use ReEvents\Data\Meta;
use ReEvents\Data\Ticket_Repository;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Event ticket endpoints.
 */
final class Tickets_Controller extends WP_REST_Controller {

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
	protected $rest_base = 'tickets';

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/events/(?P<event_id>[\d]+)/tickets',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => '__return_true',
					'args'                => $this->get_event_id_arg(),
					'response'            => 'collection',
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_item' ],
					'permission_callback' => [ $this, 'create_item_permissions_check' ],
					'args'                => array_merge( $this->get_event_id_arg(), $this->get_write_args( true ) ),
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
	 * Check whether the user may manage tickets for an event.
	 *
	 * @param int $event_id Event ID.
	 */
	private function can_manage_event( int $event_id ): bool {
		return current_user_can( 'edit_post', $event_id ) || current_user_can( 'reevents_manage_bookings' );
	}

	/**
	 * Check whether the user may create tickets.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		$event_id = (int) $request['event_id'];

		if ( $this->can_manage_event( $event_id ) ) {
			return true;
		}

		return new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to manage tickets.', 're-events' ), [ 'status' => rest_authorization_required_code() ] );
	}

	/**
	 * Check whether the user may edit the ticket.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		$ticket = ( new Ticket_Repository() )->find( (int) $request['id'] );

		if ( $ticket && $this->can_manage_event( (int) $ticket['event_id'] ) ) {
			return true;
		}

		return new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to edit this ticket.', 're-events' ), [ 'status' => rest_authorization_required_code() ] );
	}

	/**
	 * Check whether the user may delete the ticket.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function delete_item_permissions_check( $request ) {
		return $this->update_item_permissions_check( $request );
	}

	/**
	 * List tickets for an event.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$event_id = (int) $request['event_id'];

		if ( 'reevents_event' !== get_post_type( $event_id ) ) {
			return new WP_Error( 'rest_event_invalid_id', __( 'Invalid event ID.', 're-events' ), [ 'status' => 404 ] );
		}

		if ( 'publish' !== get_post_status( $event_id ) && ! $this->can_manage_event( $event_id ) ) {
			return new WP_Error( 'rest_event_invalid_id', __( 'Invalid event ID.', 're-events' ), [ 'status' => 404 ] );
		}

		$status  = $this->can_manage_event( $event_id ) ? '' : 'active';
		$tickets = ( new Ticket_Repository() )->for_event( $event_id, $status );
		$items   = [];

		foreach ( $tickets as $ticket ) {
			$items[] = $this->prepare_item_for_response( $ticket, $request )->get_data();
		}

		$response = rest_ensure_response( $items );
		$response->header( 'X-WP-Total', (string) count( $items ) );
		$response->header( 'X-WP-TotalPages', '1' );

		return $response;
	}

	/**
	 * Get a ticket.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$ticket = ( new Ticket_Repository() )->find( (int) $request['id'] );

		if ( ! $ticket ) {
			return new WP_Error( 'rest_ticket_invalid_id', __( 'Invalid ticket ID.', 're-events' ), [ 'status' => 404 ] );
		}

		if ( 'active' !== $ticket['status'] && ! $this->can_manage_event( (int) $ticket['event_id'] ) ) {
			return new WP_Error( 'rest_ticket_invalid_id', __( 'Invalid ticket ID.', 're-events' ), [ 'status' => 404 ] );
		}

		if ( 'publish' !== get_post_status( (int) $ticket['event_id'] ) && ! $this->can_manage_event( (int) $ticket['event_id'] ) ) {
			return new WP_Error( 'rest_ticket_invalid_id', __( 'Invalid ticket ID.', 're-events' ), [ 'status' => 404 ] );
		}

		return $this->prepare_item_for_response( $ticket, $request );
	}

	/**
	 * Create a ticket.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$event_id = (int) $request['event_id'];

		if ( 'reevents_event' !== get_post_type( $event_id ) ) {
			return new WP_Error( 'rest_event_invalid_id', __( 'Invalid event ID.', 're-events' ), [ 'status' => 404 ] );
		}

		$repo   = new Ticket_Repository();
		$ticket = $this->ticket_from_request( $request, [ 'event_id' => $event_id ] );

		if ( '' === $ticket['name'] ) {
			return new WP_Error( 'rest_ticket_invalid_name', __( 'A ticket name is required.', 're-events' ), [ 'status' => 400 ] );
		}

		$result = $repo->save( $ticket );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$ticket = $repo->find( (int) $result );

		if ( ! $ticket ) {
			return new WP_Error( 'rest_ticket_save_failed', __( 'The ticket could not be saved.', 're-events' ), [ 'status' => 500 ] );
		}

		$response = $this->prepare_item_for_response( $ticket, $request );
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( $this->namespace . '/' . $this->rest_base . '/' . $ticket['id'] ) );

		return $response;
	}

	/**
	 * Update a ticket.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$repo     = new Ticket_Repository();
		$existing = $repo->find( (int) $request['id'] );

		if ( ! $existing ) {
			return new WP_Error( 'rest_ticket_invalid_id', __( 'Invalid ticket ID.', 're-events' ), [ 'status' => 404 ] );
		}

		$ticket = $this->ticket_from_request( $request, $existing );
		$result = $repo->save( $ticket );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$ticket = $repo->find( (int) $request['id'] );

		if ( ! $ticket ) {
			return new WP_Error( 'rest_ticket_save_failed', __( 'The ticket could not be saved.', 're-events' ), [ 'status' => 500 ] );
		}

		return $this->prepare_item_for_response( $ticket, $request );
	}

	/**
	 * Delete a ticket.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$repo   = new Ticket_Repository();
		$ticket = $repo->find( (int) $request['id'] );

		if ( ! $ticket ) {
			return new WP_Error( 'rest_ticket_invalid_id', __( 'Invalid ticket ID.', 're-events' ), [ 'status' => 404 ] );
		}

		$previous = $this->prepare_item_for_response( $ticket, $request )->get_data();

		if ( ! $repo->delete( (int) $request['id'] ) ) {
			return new WP_Error( 'rest_ticket_delete_failed', __( 'The ticket could not be deleted.', 're-events' ), [ 'status' => 500 ] );
		}

		return rest_ensure_response(
			[
				'deleted'  => true,
				'previous' => $previous,
			]
		);
	}

	/**
	 * Build a ticket from request parameters.
	 *
	 * @param WP_REST_Request      $request Request.
	 * @param array<string, mixed> $ticket  Existing ticket.
	 * @return array<string, mixed>
	 */
	private function ticket_from_request( WP_REST_Request $request, array $ticket = [] ): array {
		$ticket = array_merge(
			[
				'id'              => 0,
				'event_id'        => 0,
				'name'            => '',
				'description'     => '',
				'price_minor'     => 0,
				'currency'        => 'USD',
				'capacity'        => 0,
				'min_per_booking' => 1,
				'max_per_booking' => 0,
				'sale_start_utc'  => '',
				'sale_end_utc'    => '',
				'status'          => 'active',
			],
			$ticket
		);

		return [
			'id'              => (int) $ticket['id'],
			'event_id'        => (int) $ticket['event_id'],
			'name'            => $request->has_param( 'name' ) ? sanitize_text_field( (string) $request['name'] ) : (string) $ticket['name'],
			'description'     => $request->has_param( 'description' ) ? sanitize_textarea_field( (string) $request['description'] ) : (string) $ticket['description'],
			'price_minor'     => $request->has_param( 'price_minor' ) ? max( 0, (int) $request['price_minor'] ) : (int) $ticket['price_minor'],
			'currency'        => $request->has_param( 'currency' ) ? $this->currency( (string) $request['currency'] ) : (string) $ticket['currency'],
			'capacity'        => $request->has_param( 'capacity' ) ? max( 0, (int) $request['capacity'] ) : (int) $ticket['capacity'],
			'min_per_booking' => $request->has_param( 'min_per_booking' ) ? max( 1, (int) $request['min_per_booking'] ) : (int) $ticket['min_per_booking'],
			'max_per_booking' => $request->has_param( 'max_per_booking' ) ? max( 0, (int) $request['max_per_booking'] ) : (int) $ticket['max_per_booking'],
			'sale_start_utc'  => $request->has_param( 'sale_start_utc' ) ? Meta::sanitize_datetime( $request['sale_start_utc'] ) : (string) $ticket['sale_start_utc'],
			'sale_end_utc'    => $request->has_param( 'sale_end_utc' ) ? Meta::sanitize_datetime( $request['sale_end_utc'] ) : (string) $ticket['sale_end_utc'],
			'status'          => $request->has_param( 'status' ) ? sanitize_key( (string) $request['status'] ) : (string) $ticket['status'],
		];
	}

	/**
	 * Sanitize a currency code.
	 *
	 * @param string $currency Raw code.
	 */
	private function currency( string $currency ): string {
		$currency = strtoupper( preg_replace( '/[^A-Za-z]/', '', $currency ) ?? '' );

		return 3 === strlen( $currency ) ? $currency : 'USD';
	}

	/**
	 * Serialize a ticket.
	 *
	 * @param array<string, mixed> $item    Ticket.
	 * @param WP_REST_Request      $request Request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $item, $request ) {
		return rest_ensure_response( $item );
	}

	/**
	 * Writable ticket parameters.
	 *
	 * @param bool $required Whether the name is required.
	 * @return array<string, mixed>
	 */
	private function get_write_args( bool $required ): array {
		return [
			'name'            => [
				'description'       => __( 'Ticket name.', 're-events' ),
				'type'              => 'string',
				'required'          => $required,
				'sanitize_callback' => 'sanitize_text_field',
			],
			'description'     => [
				'description'       => __( 'Ticket description.', 're-events' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			],
			'price_minor'     => [
				'description' => __( 'Price in the smallest currency unit.', 're-events' ),
				'type'        => 'integer',
				'minimum'     => 0,
			],
			'currency'        => [
				'description' => __( 'Three-letter currency code.', 're-events' ),
				'type'        => 'string',
			],
			'capacity'        => [
				'description' => __( 'Ticket capacity; 0 means unlimited.', 're-events' ),
				'type'        => 'integer',
				'minimum'     => 0,
			],
			'min_per_booking' => [
				'description' => __( 'Minimum seats per booking.', 're-events' ),
				'type'        => 'integer',
				'minimum'     => 1,
			],
			'max_per_booking' => [
				'description' => __( 'Maximum seats per booking; 0 means no limit.', 're-events' ),
				'type'        => 'integer',
				'minimum'     => 0,
			],
			'sale_start_utc'  => [
				'description'       => __( 'Sale start in UTC (Y-m-d H:i:s).', 're-events' ),
				'type'              => 'string',
				'validate_callback' => [ Events_Controller::class, 'validate_datetime' ],
				'sanitize_callback' => [ Meta::class, 'sanitize_datetime' ],
			],
			'sale_end_utc'    => [
				'description'       => __( 'Sale end in UTC (Y-m-d H:i:s).', 're-events' ),
				'type'              => 'string',
				'validate_callback' => [ Events_Controller::class, 'validate_datetime' ],
				'sanitize_callback' => [ Meta::class, 'sanitize_datetime' ],
			],
			'status'          => [
				'description' => __( 'Ticket status.', 're-events' ),
				'type'        => 'string',
				'enum'        => [ 'active', 'inactive' ],
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
			'title'      => 'ticket',
			'type'       => 'object',
			'properties' => [
				'id'              => [
					'description' => __( 'Unique identifier for the ticket.', 're-events' ),
					'type'        => 'integer',
					'readonly'    => true,
				],
				'event_id'        => [
					'description' => __( 'Event post ID.', 're-events' ),
					'type'        => 'integer',
				],
				'name'            => [
					'description' => __( 'Ticket name.', 're-events' ),
					'type'        => 'string',
				],
				'description'     => [
					'description' => __( 'Ticket description.', 're-events' ),
					'type'        => 'string',
				],
				'price_minor'     => [
					'description' => __( 'Price in the smallest currency unit.', 're-events' ),
					'type'        => 'integer',
				],
				'currency'        => [
					'description' => __( 'Currency code.', 're-events' ),
					'type'        => 'string',
				],
				'capacity'        => [
					'description' => __( 'Ticket capacity; 0 means unlimited.', 're-events' ),
					'type'        => 'integer',
				],
				'sold'            => [
					'description' => __( 'Seats sold.', 're-events' ),
					'type'        => 'integer',
					'readonly'    => true,
				],
				'min_per_booking' => [
					'description' => __( 'Minimum seats per booking.', 're-events' ),
					'type'        => 'integer',
				],
				'max_per_booking' => [
					'description' => __( 'Maximum seats per booking; 0 means no limit.', 're-events' ),
					'type'        => 'integer',
				],
				'sale_start_utc'  => [
					'description' => __( 'Sale start in UTC.', 're-events' ),
					'type'        => 'string',
				],
				'sale_end_utc'    => [
					'description' => __( 'Sale end in UTC.', 're-events' ),
					'type'        => 'string',
				],
				'status'          => [
					'description' => __( 'Ticket status.', 're-events' ),
					'type'        => 'string',
					'enum'        => [ 'active', 'inactive' ],
				],
			],
		];
	}

	/**
	 * Event identifier argument.
	 *
	 * @return array<string, mixed>
	 */
	private function get_event_id_arg(): array {
		return [
			'event_id' => [
				'description'       => __( 'Event post ID.', 're-events' ),
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
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
				'description'       => __( 'Unique identifier for the ticket.', 're-events' ),
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			],
		];
	}
}

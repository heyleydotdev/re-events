<?php
/**
 * Bookings REST controller.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\REST;

use ReEvents\Data\Booking_Repository;
use ReEvents\Data\Ticket_Repository;
use ReEvents\Services\Booking_Service;
use ReEvents\Services\Booking_Validator;
use ReEvents\Services\Currency;
use ReEvents\Services\Payment_Service;
use ReEvents\Services\Rate_Limit;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Booking endpoints.
 */
final class Bookings_Controller extends WP_REST_Controller {

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
	protected $rest_base = 'bookings';

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/events/(?P<event_id>[\d]+)/bookings',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
					'args'                => array_merge( $this->get_event_id_arg(), $this->get_collection_params() ),
					'response'            => 'collection',
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_item' ],
					'permission_callback' => '__return_true',
					'args'                => array_merge( $this->get_event_id_arg(), $this->get_write_args() ),
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
					'args'                => array_merge( $this->get_id_arg(), $this->get_reference_arg() ),
				],
				[
					'methods'             => 'PATCH',
					'callback'            => [ $this, 'update_item' ],
					'permission_callback' => [ $this, 'update_item_permissions_check' ],
					'args'                => array_merge( $this->get_id_arg(), $this->get_status_arg() ),
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);
	}

	/**
	 * Check whether the user may read event bookings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		if ( $this->can_manage_event( (int) $request['event_id'] ) ) {
			return true;
		}

		return new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to read bookings.', 're-events' ), [ 'status' => rest_authorization_required_code() ] );
	}

	/**
	 * Check whether the user may read the booking.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		$booking = ( new Booking_Repository() )->find( (int) $request['id'] );

		if ( ! $booking ) {
			return new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to read this booking.', 're-events' ), [ 'status' => rest_authorization_required_code() ] );
		}

		if ( $this->can_manage_event( (int) $booking['event_id'] ) ) {
			return true;
		}

		$reference = (string) $request['reference'];

		if ( '' !== $reference && '' !== $booking['reference'] && hash_equals( (string) $booking['reference'], $reference ) ) {
			return true;
		}

		return new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to read this booking.', 're-events' ), [ 'status' => rest_authorization_required_code() ] );
	}

	/**
	 * Check whether the user may update the booking.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		$booking = ( new Booking_Repository() )->find( (int) $request['id'] );

		if ( $booking && $this->can_manage_event( (int) $booking['event_id'] ) ) {
			return true;
		}

		return new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to update this booking.', 're-events' ), [ 'status' => rest_authorization_required_code() ] );
	}

	/**
	 * Check whether the user may manage an event.
	 *
	 * @param int $event_id Event ID.
	 */
	private function can_manage_event( int $event_id ): bool {
		return current_user_can( 'reevents_manage_bookings' ) || current_user_can( 'edit_post', $event_id );
	}

	/**
	 * List bookings for an event.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$event_id = (int) $request['event_id'];

		if ( 'reevents_event' !== get_post_type( $event_id ) ) {
			return new WP_Error( 'rest_event_invalid_id', __( 'Invalid event ID.', 're-events' ), [ 'status' => 404 ] );
		}

		$per_page = min( 100, max( 1, (int) $request['per_page'] ) );
		$page     = max( 1, (int) $request['page'] );
		$status   = (string) $request['status'];
		$repo     = new Booking_Repository();
		$rows     = $repo->for_event( $event_id, $status, $per_page, ( $page - 1 ) * $per_page );
		$total    = $repo->count_for_event( $event_id, $status );
		$items    = [];

		foreach ( $rows as $booking ) {
			$items[] = $this->prepare_booking( $booking, false );
		}

		$response = rest_ensure_response( $items );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) (int) ceil( $total / $per_page ) );

		return $response;
	}

	/**
	 * Get a booking.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$booking = ( new Booking_Repository() )->find( (int) $request['id'] );

		if ( ! $booking ) {
			return new WP_Error( 'rest_booking_invalid_id', __( 'Invalid booking ID.', 're-events' ), [ 'status' => 404 ] );
		}

		return $this->prepare_item_for_response( $booking, $request );
	}

	/**
	 * Create a booking.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		if ( '' !== (string) $request['website'] ) {
			return new WP_Error( 'rest_booking_rejected', __( 'The booking could not be created.', 're-events' ), [ 'status' => 400 ] );
		}

		$token     = sanitize_text_field( (string) $request['booking_token'] );
		$event_id  = (int) $request['event_id'];
		$cache_key = Booking_Service::token_cache_key( $token, $event_id );

		if ( '' !== $cache_key ) {
			$existing = get_transient( $cache_key );

			if ( is_numeric( $existing ) ) {
				$booking = ( new Booking_Repository() )->find( (int) $existing );

				if ( $booking ) {
					$response = $this->prepare_item_for_response( $booking, $request );
					$checkout = Payment_Service::checkout( (int) $booking['id'] );

					if ( ! is_wp_error( $checkout ) && '' !== $checkout ) {
						$data                 = $response->get_data();
						$data['checkout_url'] = $checkout;
						$response->set_data( $data );
					}

					return $response;
				}
			}
		}

		if ( Rate_Limit::hit( 'booking' ) ) {
			return new WP_Error( 'rest_booking_rate_limited', __( 'Too many booking attempts. Try again later.', 're-events' ), [ 'status' => 429 ] );
		}

		$attendees = (array) $request['attendees'];
		$quantity  = max( 1, (int) $request['quantity'] );
		$errors    = Booking_Validator::validate(
			[
				'event_id'      => (int) $request['event_id'],
				'ticket_id'     => (int) $request['ticket_id'],
				'quantity'      => $quantity,
				'attendees'     => $attendees,
				'booking_token' => $token,
			]
		);

		if ( $errors ) {
			return new WP_Error(
				'rest_booking_invalid',
				__( 'Please correct the booking fields.', 're-events' ),
				[
					'status' => 400,
					'errors' => $errors,
				]
			);
		}

		if ( '' !== $cache_key && ! Booking_Service::claim_token( $token, $event_id ) ) {
			return new WP_Error( 'rest_booking_in_progress', __( 'This booking is already being processed. Please wait a moment.', 're-events' ), [ 'status' => 409 ] );
		}

		try {
			$result = Booking_Service::create(
				[
					'event_id'      => (int) $request['event_id'],
					'ticket_id'     => (int) $request['ticket_id'],
					'occurrence_id' => (int) $request['occurrence_id'],
					'quantity'      => $quantity,
					'attendees'     => $attendees,
				]
			);

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$booking = ( new Booking_Repository() )->find( (int) $result );

			if ( ! $booking ) {
				return new WP_Error( 'rest_booking_save_failed', __( 'The booking could not be saved.', 're-events' ), [ 'status' => 500 ] );
			}

			$response = $this->prepare_item_for_response( $booking, $request );
			$response->set_status( 201 );
			$response->header( 'Location', rest_url( $this->namespace . '/' . $this->rest_base . '/' . $booking['id'] ) );

			$checkout = Payment_Service::checkout( (int) $booking['id'] );

			if ( is_wp_error( $checkout ) ) {
				Booking_Service::cancel( (int) $booking['id'] );
				return $checkout;
			}

			if ( '' !== $cache_key ) {
				set_transient( $cache_key, (int) $result, 5 * MINUTE_IN_SECONDS );
			}

			if ( '' !== $checkout ) {
				$data                 = $response->get_data();
				$data['checkout_url'] = $checkout;
				$response->set_data( $data );
			}

			return $response;
		} finally {
			Booking_Service::release_token( $token, $event_id );
		}
	}

	/**
	 * Update a booking status.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$result = Booking_Service::update_status( (int) $request['id'], (string) $request['status'] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$booking = ( new Booking_Repository() )->find( (int) $request['id'] );

		if ( ! $booking ) {
			return new WP_Error( 'rest_booking_invalid_id', __( 'Invalid booking ID.', 're-events' ), [ 'status' => 404 ] );
		}

		return $this->prepare_item_for_response( $booking, $request );
	}

	/**
	 * Serialize a booking.
	 *
	 * @param array<string, mixed> $item    Booking.
	 * @param WP_REST_Request      $request Request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $item, $request ) {
		return rest_ensure_response( $this->prepare_booking( $item, true ) );
	}

	/**
	 * Build booking data.
	 *
	 * @param array<string, mixed> $booking         Booking.
	 * @param bool                 $with_attendees  Include attendees.
	 * @return array<string, mixed>
	 */
	private function prepare_booking( array $booking, bool $with_attendees ): array {
		$data = [
			'id'              => (int) $booking['id'],
			'reference'       => (string) $booking['reference'],
			'event_title'     => (string) ( $booking['event_title'] ?? get_the_title( (int) $booking['event_id'] ) ),
			'event_id'        => (int) $booking['event_id'],
			'occurrence_id'   => (int) $booking['occurrence_id'],
			'ticket_id'       => (int) $booking['ticket_id'],
			'user_id'         => (int) $booking['user_id'],
			'quantity'        => (int) $booking['quantity'],
			'status'          => (string) $booking['status'],
			'total_minor'     => (int) $booking['total_minor'],
			'total_formatted' => (int) $booking['total_minor'] > 0 ? Currency::format( (int) $booking['total_minor'], (string) $booking['currency'] ) : __( 'Free', 're-events' ),
			'currency'        => (string) $booking['currency'],
			'created_utc'     => (string) $booking['created_utc'],
			'updated_utc'     => (string) $booking['updated_utc'],
		];

		if ( $with_attendees ) {
			$ticket = ( new Ticket_Repository() )->find( (int) $booking['ticket_id'] );

			$data['ticket_name'] = (string) ( $ticket['name'] ?? '' );
			$data['attendees']   = ( new Booking_Repository() )->attendees( (int) $booking['id'] );
		}

		return $data;
	}

	/**
	 * Writable booking parameters.
	 *
	 * @return array<string, mixed>
	 */
	private function get_write_args(): array {
		return [
			'ticket_id'     => [
				'description'       => __( 'Ticket ID.', 're-events' ),
				'type'              => 'integer',
				'required'          => true,
				'sanitize_callback' => 'absint',
			],
			'occurrence_id' => [
				'description'       => __( 'Occurrence ID.', 're-events' ),
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			],
			'quantity'      => [
				'description'       => __( 'Number of seats.', 're-events' ),
				'type'              => 'integer',
				'minimum'           => 1,
				'maximum'           => Booking_Validator::MAX_SEATS,
				'sanitize_callback' => 'absint',
			],
			'attendees'     => [
				'description' => __( 'Attendees.', 're-events' ),
				'type'        => 'array',
				'maxItems'    => Booking_Validator::MAX_ATTENDEES,
				'items'       => [
					'type'       => 'object',
					'properties' => [
						'name'   => [ 'type' => 'string' ],
						'email'  => [ 'type' => 'string' ],
						'fields' => [
							'type'                 => 'object',
							'additionalProperties' => [ 'type' => 'string' ],
						],
					],
				],
			],
			'website'       => [
				'description' => __( 'Leave empty.', 're-events' ),
				'type'        => 'string',
			],
			'booking_token' => [
				'description'       => __( 'Idempotency token for retries.', 're-events' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
		];
	}

	/**
	 * Collection parameters.
	 *
	 * @return array<string, mixed>
	 */
	public function get_collection_params() {
		$params = parent::get_collection_params();

		$params['status'] = [
			'description'       => __( 'Limit results to a booking status.', 're-events' ),
			'type'              => 'string',
			'enum'              => [ 'pending', 'confirmed', 'cancelled', 'rejected' ],
			'sanitize_callback' => 'sanitize_key',
		];

		return $params;
	}

	/**
	 * Item schema.
	 *
	 * @return array<string, mixed>
	 */
	public function get_item_schema() {
		return [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'booking',
			'type'       => 'object',
			'properties' => [
				'id'              => [
					'description' => __( 'Unique identifier for the booking.', 're-events' ),
					'type'        => 'integer',
					'readonly'    => true,
				],
				'reference'       => [
					'description' => __( 'Booking reference.', 're-events' ),
					'type'        => 'string',
					'readonly'    => true,
				],
				'event_title'     => [
					'description' => __( 'Event title.', 're-events' ),
					'type'        => 'string',
					'readonly'    => true,
				],
				'ticket_name'     => [
					'description' => __( 'Ticket name.', 're-events' ),
					'type'        => 'string',
					'readonly'    => true,
				],
				'event_id'        => [
					'description' => __( 'Event post ID.', 're-events' ),
					'type'        => 'integer',
				],
				'occurrence_id'   => [
					'description' => __( 'Occurrence ID.', 're-events' ),
					'type'        => 'integer',
				],
				'ticket_id'       => [
					'description' => __( 'Ticket ID.', 're-events' ),
					'type'        => 'integer',
				],
				'user_id'         => [
					'description' => __( 'User ID.', 're-events' ),
					'type'        => 'integer',
				],
				'quantity'        => [
					'description' => __( 'Number of seats.', 're-events' ),
					'type'        => 'integer',
				],
				'status'          => [
					'description' => __( 'Booking status.', 're-events' ),
					'type'        => 'string',
					'enum'        => [ 'pending', 'confirmed', 'cancelled', 'rejected' ],
				],
				'total_minor'     => [
					'description' => __( 'Total in the smallest currency unit.', 're-events' ),
					'type'        => 'integer',
				],
				'total_formatted' => [
					'description' => __( 'Formatted total.', 're-events' ),
					'type'        => 'string',
					'readonly'    => true,
				],
				'currency'        => [
					'description' => __( 'Currency code.', 're-events' ),
					'type'        => 'string',
				],
				'created_utc'     => [
					'description' => __( 'Creation time in UTC.', 're-events' ),
					'type'        => 'string',
				],
				'updated_utc'     => [
					'description' => __( 'Last update in UTC.', 're-events' ),
					'type'        => 'string',
				],
				'attendees'       => [
					'description' => __( 'Attendees.', 're-events' ),
					'type'        => 'array',
					'items'       => [
						'type'       => 'object',
						'properties' => [
							'id'         => [ 'type' => 'integer' ],
							'name'       => [ 'type' => 'string' ],
							'email'      => [ 'type' => 'string' ],
							'checked_in' => [ 'type' => 'boolean' ],
						],
					],
				],
			],
		];
	}

	/**
	 * Status argument.
	 *
	 * @return array<string, mixed>
	 */
	private function get_status_arg(): array {
		return [
			'status' => [
				'description'       => __( 'Booking status.', 're-events' ),
				'type'              => 'string',
				'required'          => true,
				'enum'              => [ 'pending', 'confirmed', 'cancelled', 'rejected' ],
				'sanitize_callback' => 'sanitize_key',
			],
		];
	}

	/**
	 * Reference argument.
	 *
	 * @return array<string, mixed>
	 */
	private function get_reference_arg(): array {
		return [
			'reference' => [
				'description'       => __( 'Booking reference.', 're-events' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
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
				'description'       => __( 'Unique identifier for the booking.', 're-events' ),
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			],
		];
	}
}

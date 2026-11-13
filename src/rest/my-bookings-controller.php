<?php
/**
 * Self-service bookings REST controller.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\REST;

use ReEvents\Data\Booking_Repository;
use ReEvents\Data\Event_Repository;
use ReEvents\Data\Occurrence_Repository;
use ReEvents\Data\Ticket_Repository;
use ReEvents\Domain\Event;
use ReEvents\Services\Booking_Access;
use ReEvents\Services\Booking_Self_Service;
use ReEvents\Services\Currency;
use ReEvents\Services\Rate_Limit;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Visitor-facing booking endpoints.
 */
final class My_Bookings_Controller extends WP_REST_Controller {

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
	protected $rest_base = 'my-bookings';

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
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
					'args'                => $this->get_collection_params(),
					'summary'             => __( 'List My Bookings', 're-events' ),
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/lookup',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'lookup' ],
					'permission_callback' => '__return_true',
					'args'                => [
						'reference' => [
							'description'       => __( 'Booking reference.', 're-events' ),
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'email'     => [
							'description'       => __( 'Attendee email used for the booking.', 're-events' ),
							'type'              => 'string',
							'format'            => 'email',
							'required'          => true,
							'sanitize_callback' => 'sanitize_email',
						],
					],
					'response'            => 'item',
					'summary'             => __( 'Find Booking', 're-events' ),
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
					'args'                => array_merge( $this->get_id_arg(), $this->get_access_args() ),
					'summary'             => __( 'Get My Booking', 're-events' ),
				],
				[
					'methods'             => 'PATCH',
					'callback'            => [ $this, 'update_item' ],
					'permission_callback' => [ $this, 'update_item_permissions_check' ],
					'args'                => array_merge(
						$this->get_id_arg(),
						$this->get_access_args(),
						[
							'action' => [
								'description' => __( 'cancel or reactivate.', 're-events' ),
								'type'        => 'string',
								'enum'        => [ 'cancel', 'reactivate' ],
								'required'    => true,
							],
						]
					),
					'summary'             => __( 'Update My Booking', 're-events' ),
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);
	}

	/**
	 * Owners only.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		unset( $request );

		if ( ! Booking_Self_Service::enabled() ) {
			return new WP_Error( 'reevents_self_service_disabled', __( 'Booking changes are disabled.', 're-events' ), [ 'status' => 403 ] );
		}

		if ( is_user_logged_in() ) {
			return true;
		}

		return new WP_Error( 'rest_forbidden', __( 'Authentication required.', 're-events' ), [ 'status' => 401 ] );
	}

	/**
	 * Owners, signed links or reference plus email.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		return $this->check_access( $request );
	}

	/**
	 * Same access rules as reading.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		return $this->check_access( $request );
	}

	/**
	 * List the current user's bookings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$per_page = min( 50, max( 1, (int) $request['per_page'] ) );
		$page     = max( 1, (int) $request['page'] );
		$result   = ( new Booking_Repository() )->query(
			[
				'user_id' => get_current_user_id(),
				'limit'   => $per_page,
				'offset'  => ( $page - 1 ) * $per_page,
				'status'  => sanitize_key( (string) $request['status'] ),
				'search'  => sanitize_text_field( (string) $request['search'] ),
			]
		);
		$items    = $this->prepare_many( $result['items'] );

		$response = rest_ensure_response( $items );
		$response->header( 'X-WP-Total', (string) $result['total'] );
		$response->header( 'X-WP-TotalPages', (string) (int) ceil( $result['total'] / $per_page ) );

		return $response;
	}

	/**
	 * Look up one booking for a guest.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function lookup( $request ) {
		if ( ! Booking_Self_Service::enabled() ) {
			return new WP_Error( 'reevents_self_service_disabled', __( 'Booking changes are disabled.', 're-events' ), [ 'status' => 403 ] );
		}

		if ( Rate_Limit::hit( 'booking_lookup', 10, MINUTE_IN_SECONDS ) ) {
			return new WP_Error( 'rest_rate_limited', __( 'Too many lookups. Please try again later.', 're-events' ), [ 'status' => 429 ] );
		}

		$booking = Booking_Access::lookup( (string) $request['reference'], (string) $request['email'] );

		if ( ! $booking ) {
			return new WP_Error( 'reevents_booking_not_found', __( 'No booking matches that reference and email.', 're-events' ), [ 'status' => 404 ] );
		}

		return rest_ensure_response( $this->prepare_booking( $booking ) );
	}

	/**
	 * Read one booking.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$booking = ( new Booking_Repository() )->find( (int) $request['id'] );

		if ( ! $booking ) {
			return new WP_Error( 'reevents_booking_invalid_id', __( 'Invalid booking ID.', 're-events' ), [ 'status' => 404 ] );
		}

		return rest_ensure_response( $this->prepare_booking( $booking ) );
	}

	/**
	 * Change a booking.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$id     = (int) $request['id'];
		$result = 'reactivate' === (string) $request['action']
			? Booking_Self_Service::reactivate( $id )
			: Booking_Self_Service::cancel( $id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$booking = ( new Booking_Repository() )->find( $id );

		if ( ! $booking ) {
			return new WP_Error( 'reevents_booking_invalid_id', __( 'Invalid booking ID.', 're-events' ), [ 'status' => 404 ] );
		}

		return rest_ensure_response( $this->prepare_booking( $booking ) );
	}

	/**
	 * Shared access check.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	private function check_access( WP_REST_Request $request ) {
		if ( ! Booking_Self_Service::enabled() ) {
			return new WP_Error( 'reevents_self_service_disabled', __( 'Booking changes are disabled.', 're-events' ), [ 'status' => 403 ] );
		}

		$booking = ( new Booking_Repository() )->find( (int) $request['id'] );

		if ( ! $booking || ! Booking_Access::authorize( $booking, $this->credentials( $request ) ) ) {
			return new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to access this booking.', 're-events' ), [ 'status' => 403 ] );
		}

		return true;
	}

	/**
	 * Credentials from the request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, string>
	 */
	private function credentials( WP_REST_Request $request ): array {
		return [
			'key'       => (string) $request->get_param( 'key' ),
			'reference' => (string) $request->get_param( 'reference' ),
			'email'     => (string) $request->get_param( 'email' ),
		];
	}

	/**
	 * Serialize many bookings with batched lookups.
	 *
	 * @param array<int, array<string, mixed>> $bookings Bookings.
	 * @return array<int, array<string, mixed>>
	 */
	private function prepare_many( array $bookings ): array {
		if ( ! $bookings ) {
			return [];
		}

		$event_ids = array_map( static fn( array $booking ): int => (int) $booking['event_id'], $bookings );
		$events    = ( new Event_Repository() )->find_many( $event_ids );
		$rows      = ( new Occurrence_Repository() )->find_many(
			array_map( static fn( array $booking ): int => (int) $booking['occurrence_id'], $bookings )
		);
		$rules     = Booking_Self_Service::rules_many( $bookings, $events, $rows );
		$attendees = ( new Booking_Repository() )->attendees_many(
			array_map( static fn( array $booking ): int => (int) $booking['id'], $bookings )
		);
		$tickets   = [];

		foreach ( ( new Ticket_Repository() )->for_events( $event_ids ) as $event_tickets ) {
			foreach ( $event_tickets as $ticket ) {
				$tickets[ (int) $ticket['id'] ] = $ticket;
			}
		}

		$items = [];

		foreach ( $bookings as $booking ) {
			$id      = (int) $booking['id'];
			$items[] = $this->prepare_booking(
				$booking,
				$rules[ $id ] ?? [],
				$events[ (int) $booking['event_id'] ] ?? null,
				$tickets[ (int) $booking['ticket_id'] ] ?? null,
				$rows[ (int) $booking['occurrence_id'] ] ?? null,
				$attendees[ $id ] ?? []
			);
		}

		return $items;
	}

	/**
	 * Serialize a booking.
	 *
	 * @param array<string, mixed>                  $booking     Booking.
	 * @param array<string, mixed>|null             $rules       Preloaded rules.
	 * @param Event|null                            $event       Preloaded event.
	 * @param array<string, mixed>|null             $ticket      Preloaded ticket.
	 * @param array<string, mixed>|null             $occurrence  Preloaded occurrence.
	 * @param array<int, array<string, mixed>>|null $attendees Preloaded attendees.
	 * @return array<string, mixed>
	 */
	private function prepare_booking( array $booking, ?array $rules = null, ?Event $event = null, ?array $ticket = null, ?array $occurrence = null, ?array $attendees = null ): array {
		$event      = $event ?? ( new Event_Repository() )->find( (int) $booking['event_id'] );
		$occurrence = $occurrence ?? ( (int) $booking['occurrence_id'] > 0
			? ( new Occurrence_Repository() )->find( (int) $booking['occurrence_id'] )
			: null );
		$ticket     = $ticket ?? ( new Ticket_Repository() )->find( (int) $booking['ticket_id'] );
		$rules      = $rules ?? Booking_Self_Service::rules( $booking );
		$attendees  = $attendees ?? ( new Booking_Repository() )->attendees( (int) $booking['id'] );

		return [
			'id'                => (int) $booking['id'],
			'reference'         => (string) $booking['reference'],
			'event_id'          => (int) $booking['event_id'],
			'event_title'       => $event ? $event->title : (string) ( $booking['event_title'] ?? '' ),
			'event_url'         => $event ? (string) get_permalink( $event->id ) : '',
			'occurrence_id'     => (int) $booking['occurrence_id'],
			'start_utc'         => (string) ( $rules['start_utc'] ?? '' ),
			'ticket_id'         => (int) $booking['ticket_id'],
			'ticket_name'       => (string) ( $ticket['name'] ?? '' ),
			'quantity'          => (int) $booking['quantity'],
			'status'            => (string) $booking['status'],
			'total_minor'       => (int) $booking['total_minor'],
			'total_formatted'   => (int) $booking['total_minor'] > 0
				? Currency::format( (int) $booking['total_minor'], (string) $booking['currency'] )
				: __( 'Free', 're-events' ),
			'currency'          => (string) $booking['currency'],
			'created_utc'       => (string) $booking['created_utc'],
			'can_cancel'        => ! empty( $rules['can_cancel'] ),
			'can_reactivate'    => ! empty( $rules['can_reactivate'] ),
			'refund_on_cancel'  => ! empty( $rules['refund_on_cancel'] ),
			'cancel_reason'     => (string) ( $rules['cancel_reason'] ?? '' ),
			'reactivate_reason' => (string) ( $rules['reactivate_reason'] ?? '' ),
			'attendees'         => $attendees,
		];
	}

	/**
	 * ID argument.
	 *
	 * @return array<string, mixed>
	 */
	private function get_id_arg(): array {
		return [
			'id' => [
				'description'       => __( 'Booking ID.', 're-events' ),
				'type'              => 'integer',
				'required'          => true,
				'sanitize_callback' => 'absint',
			],
		];
	}

	/**
	 * Credential arguments.
	 *
	 * @return array<string, mixed>
	 */
	private function get_access_args(): array {
		return [
			'key'       => [
				'description'       => __( 'Signed key from a booking email.', 're-events' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'reference' => [
				'description'       => __( 'Booking reference.', 're-events' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'email'     => [
				'description'       => __( 'Attendee email.', 're-events' ),
				'type'              => 'string',
				'format'            => 'email',
				'sanitize_callback' => 'sanitize_email',
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
			'title'      => 'my-booking',
			'type'       => 'object',
			'properties' => [
				'id'                => [
					'description' => __( 'Booking ID.', 're-events' ),
					'type'        => 'integer',
					'readonly'    => true,
				],
				'reference'         => [
					'description' => __( 'Booking reference.', 're-events' ),
					'type'        => 'string',
					'readonly'    => true,
				],
				'event_id'          => [
					'description' => __( 'Event ID.', 're-events' ),
					'type'        => 'integer',
					'readonly'    => true,
				],
				'event_title'       => [
					'description' => __( 'Event title.', 're-events' ),
					'type'        => 'string',
					'readonly'    => true,
				],
				'event_url'         => [
					'description' => __( 'Event permalink.', 're-events' ),
					'type'        => 'string',
					'format'      => 'uri',
					'readonly'    => true,
				],
				'occurrence_id'     => [
					'description' => __( 'Occurrence ID.', 're-events' ),
					'type'        => 'integer',
					'readonly'    => true,
				],
				'start_utc'         => [
					'description' => __( 'Start in UTC.', 're-events' ),
					'type'        => 'string',
					'readonly'    => true,
				],
				'ticket_id'         => [
					'description' => __( 'Ticket ID.', 're-events' ),
					'type'        => 'integer',
					'readonly'    => true,
				],
				'ticket_name'       => [
					'description' => __( 'Ticket name.', 're-events' ),
					'type'        => 'string',
					'readonly'    => true,
				],
				'quantity'          => [
					'description' => __( 'Seats.', 're-events' ),
					'type'        => 'integer',
					'readonly'    => true,
				],
				'status'            => [
					'description' => __( 'Booking status.', 're-events' ),
					'type'        => 'string',
					'readonly'    => true,
				],
				'total_minor'       => [
					'description' => __( 'Total in the smallest currency unit.', 're-events' ),
					'type'        => 'integer',
					'readonly'    => true,
				],
				'total_formatted'   => [
					'description' => __( 'Formatted total.', 're-events' ),
					'type'        => 'string',
					'readonly'    => true,
				],
				'currency'          => [
					'description' => __( 'Currency code.', 're-events' ),
					'type'        => 'string',
					'readonly'    => true,
				],
				'created_utc'       => [
					'description' => __( 'Creation time in UTC.', 're-events' ),
					'type'        => 'string',
					'readonly'    => true,
				],
				'can_cancel'        => [
					'description' => __( 'Whether the booking can be cancelled.', 're-events' ),
					'type'        => 'boolean',
					'readonly'    => true,
				],
				'can_reactivate'    => [
					'description' => __( 'Whether the booking can be restored.', 're-events' ),
					'type'        => 'boolean',
					'readonly'    => true,
				],
				'refund_on_cancel'  => [
					'description' => __( 'Whether cancelling refunds a payment.', 're-events' ),
					'type'        => 'boolean',
					'readonly'    => true,
				],
				'cancel_reason'     => [
					'description' => __( 'Why cancelling is blocked.', 're-events' ),
					'type'        => 'string',
					'readonly'    => true,
				],
				'reactivate_reason' => [
					'description' => __( 'Why restoring is blocked.', 're-events' ),
					'type'        => 'string',
					'readonly'    => true,
				],
				'attendees'         => [
					'description' => __( 'Attendees.', 're-events' ),
					'type'        => 'array',
					'readonly'    => true,
					'items'       => [
						'type'       => 'object',
						'properties' => [
							'name'   => [ 'type' => 'string' ],
							'email'  => [ 'type' => 'string' ],
							'fields' => [ 'type' => 'object' ],
						],
					],
				],
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

		$params['per_page']['maximum'] = 50;

		$params['status'] = [
			'description'       => __( 'Limit results to a booking status.', 're-events' ),
			'type'              => 'string',
			'enum'              => [ 'pending', 'confirmed', 'cancelled', 'rejected' ],
			'sanitize_callback' => 'sanitize_key',
		];

		return $params;
	}
}

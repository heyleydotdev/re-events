<?php
/**
 * Events REST controller.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\REST;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use ReEvents\Data\Event_Repository;
use ReEvents\Data\Meta;
use ReEvents\Data\Occurrence_Repository;
use ReEvents\Domain\Event;
use ReEvents\Query\Event_Query;
use ReEvents\Services\Occurrence_Service;
use RRule\RRule;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Events endpoints.
 */
final class Events_Controller extends WP_REST_Controller {

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
	protected $rest_base = 'events';

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
					'args'                => [
						'id' => [
							'description'       => __( 'Unique identifier for the event.', 're-events' ),
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						],
					],
				],
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update_item' ],
					'permission_callback' => [ $this, 'update_item_permissions_check' ],
					'args'                => $this->get_write_args( false ),
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_item' ],
					'permission_callback' => [ $this, 'delete_item_permissions_check' ],
					'args'                => [
						'id' => [
							'description'       => __( 'Unique identifier for the event.', 're-events' ),
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						],
					],
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/occurrences',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_occurrences' ],
					'permission_callback' => [ $this, 'get_item_permissions_check' ],
					'args'                => [
						'id' => [
							'description'       => __( 'Unique identifier for the event.', 're-events' ),
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						],
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/occurrences/sync',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'sync_occurrences' ],
					'permission_callback' => [ $this, 'update_item_permissions_check' ],
					'args'                => [
						'id' => [
							'description'       => __( 'Unique identifier for the event.', 're-events' ),
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						],
					],
				],
			]
		);
	}

	/**
	 * Public list access.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function get_items_permissions_check( $request ) {
		return true;
	}

	/**
	 * Public item access, or edit access for non-published events.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		$post = get_post( (int) $request['id'] );

		if ( ! $post || 'reevents_event' !== $post->post_type ) {
			return new WP_Error( 'rest_event_invalid_id', __( 'Invalid event ID.', 're-events' ), [ 'status' => 404 ] );
		}

		if ( 'publish' === $post->post_status || current_user_can( 'edit_post', $post->ID ) ) {
			return true;
		}

		return new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to view this event.', 're-events' ), [ 'status' => rest_authorization_required_code() ] );
	}

	/**
	 * Check whether the user may create events.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		if ( current_user_can( 'publish_reevents_events' ) ) {
			return true;
		}

		return new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to create events.', 're-events' ), [ 'status' => rest_authorization_required_code() ] );
	}

	/**
	 * Check whether the user may edit the event.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		if ( current_user_can( 'edit_post', (int) $request['id'] ) ) {
			return true;
		}

		return new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to edit this event.', 're-events' ), [ 'status' => rest_authorization_required_code() ] );
	}

	/**
	 * Check whether the user may delete the event.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function delete_item_permissions_check( $request ) {
		if ( current_user_can( 'delete_post', (int) $request['id'] ) ) {
			return true;
		}

		return new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to delete this event.', 're-events' ), [ 'status' => rest_authorization_required_code() ] );
	}

	/**
	 * List events.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$result = Event_Query::query(
			[
				'per_page' => min( 100, max( 1, (int) $request['per_page'] ) ),
				'page'     => max( 1, (int) $request['page'] ),
				'search'   => (string) $request['search'],
				'category' => (int) $request['category'],
				'venue'    => (int) $request['venue'],
				'from'     => (string) $request['from'],
				'to'       => (string) $request['to'],
				'upcoming' => (bool) $request['upcoming'],
			]
		);
		$repo   = new Event_Repository();
		$items  = [];

		foreach ( $result['ids'] as $id ) {
			$event = $repo->find( $id );

			if ( $event ) {
				$items[] = (array) $this->prepare_item_for_response( $event, $request )->get_data();
			}
		}

		$response = rest_ensure_response( $items );
		$response->header( 'X-WP-Total', (string) $result['total'] );
		$response->header( 'X-WP-TotalPages', (string) $result['pages'] );

		return $response;
	}

	/**
	 * Get a single event.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$event = ( new Event_Repository() )->find( (int) $request['id'] );

		if ( ! $event ) {
			return new WP_Error( 'rest_event_invalid_id', __( 'Invalid event ID.', 're-events' ), [ 'status' => 404 ] );
		}

		return $this->prepare_item_for_response( $event, $request );
	}

	/**
	 * Get stored occurrences for an event.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_occurrences( $request ) {
		$event = ( new Event_Repository() )->find( (int) $request['id'] );

		if ( ! $event ) {
			return new WP_Error( 'rest_event_invalid_id', __( 'Invalid event ID.', 're-events' ), [ 'status' => 404 ] );
		}

		$rows  = ( new Occurrence_Repository() )->get_for_event( $event->id );
		$items = [];

		foreach ( $rows as $row ) {
			$items[] = [
				'id'           => (int) ( $row['id'] ?? 0 ),
				'event_id'     => (int) ( $row['event_id'] ?? 0 ),
				'start_utc'    => (string) ( $row['start_utc'] ?? '' ),
				'end_utc'      => (string) ( $row['end_utc'] ?? '' ),
				'timezone'     => (string) ( $row['timezone'] ?? '' ),
				'timeslot_key' => (string) ( $row['timeslot_key'] ?? '' ),
				'capacity'     => (int) ( $row['capacity'] ?? 0 ),
				'status'       => (string) ( $row['status'] ?? '' ),
			];
		}

		return rest_ensure_response( $items );
	}

	/**
	 * Create an event.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$repo  = new Event_Repository();
		$event = $this->event_from_request( $request );
		$error = $this->validate_event( $event );

		if ( is_wp_error( $error ) ) {
			return $error;
		}

		$result = $repo->save( $event );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$event = $repo->find( (int) $result );

		if ( ! $event ) {
			return new WP_Error( 'rest_event_save_failed', __( 'The event could not be saved.', 're-events' ), [ 'status' => 500 ] );
		}

		Occurrence_Service::sync( $event->id );

		$response = $this->prepare_item_for_response( $event, $request );
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( $this->namespace . '/' . $this->rest_base . '/' . $event->id ) );

		return $response;
	}

	/**
	 * Update an event.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$repo     = new Event_Repository();
		$existing = $repo->find( (int) $request['id'] );

		if ( ! $existing ) {
			return new WP_Error( 'rest_event_invalid_id', __( 'Invalid event ID.', 're-events' ), [ 'status' => 404 ] );
		}

		$event = $this->event_from_request( $request, $existing );
		$error = $this->validate_event( $event );

		if ( is_wp_error( $error ) ) {
			return $error;
		}

		$result = $repo->save( $event );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		Occurrence_Service::sync( $existing->id );

		$event = $repo->find( $existing->id );

		if ( ! $event ) {
			return new WP_Error( 'rest_event_save_failed', __( 'The event could not be saved.', 're-events' ), [ 'status' => 500 ] );
		}

		return $this->prepare_item_for_response( $event, $request );
	}

	/**
	 * Delete an event and its occurrences.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$repo  = new Event_Repository();
		$event = $repo->find( (int) $request['id'] );

		if ( ! $event ) {
			return new WP_Error( 'rest_event_invalid_id', __( 'Invalid event ID.', 're-events' ), [ 'status' => 404 ] );
		}

		$previous = $this->prepare_item_for_response( $event, $request )->get_data();

		if ( ! $repo->delete( $event->id ) ) {
			return new WP_Error( 'rest_event_delete_failed', __( 'The event could not be deleted.', 're-events' ), [ 'status' => 500 ] );
		}

		( new Occurrence_Repository() )->delete_for_event( $event->id );

		return rest_ensure_response(
			[
				'deleted'  => true,
				'previous' => $previous,
			]
		);
	}

	/**
	 * Rebuild stored occurrences for an event.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function sync_occurrences( $request ) {
		$event = ( new Event_Repository() )->find( (int) $request['id'] );

		if ( ! $event ) {
			return new WP_Error( 'rest_event_invalid_id', __( 'Invalid event ID.', 're-events' ), [ 'status' => 404 ] );
		}

		Occurrence_Service::sync( $event->id );

		return $this->get_occurrences( $request );
	}

	/**
	 * Build an event from request parameters, keeping existing values on partial updates.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param Event|null      $event   Existing event for updates.
	 */
	private function event_from_request( WP_REST_Request $request, ?Event $event = null ): Event {
		$event ??= new Event();

		return new Event(
			$event->id,
			$request->has_param( 'title' ) ? sanitize_text_field( (string) $request['title'] ) : $event->title,
			$request->has_param( 'start_utc' ) ? Meta::sanitize_datetime( $request['start_utc'] ) : $event->start_utc,
			$request->has_param( 'end_utc' ) ? Meta::sanitize_datetime( $request['end_utc'] ) : $event->end_utc,
			$request->has_param( 'timezone' ) ? Meta::sanitize_timezone( $request['timezone'] ) : $event->timezone,
			$request->has_param( 'all_day' ) ? (bool) $request['all_day'] : $event->all_day,
			$request->has_param( 'rrule' ) ? sanitize_text_field( (string) $request['rrule'] ) : $event->rrule,
			$request->has_param( 'exdates' ) ? Meta::sanitize_string_array( $request['exdates'] ) : $event->exdates,
			$request->has_param( 'rdates' ) ? Meta::sanitize_string_array( $request['rdates'] ) : $event->rdates,
			$request->has_param( 'timeslots' ) ? Meta::sanitize_timeslots( $request['timeslots'] ) : $event->timeslots,
			$request->has_param( 'venue_id' ) ? absint( $request['venue_id'] ) : $event->venue_id,
			$request->has_param( 'organizer_ids' ) ? Meta::sanitize_id_array( $request['organizer_ids'] ) : $event->organizer_ids,
			$request->has_param( 'capacity' ) ? absint( $request['capacity'] ) : $event->capacity,
			$request->has_param( 'online_urls' ) ? Meta::sanitize_urls( $request['online_urls'] ) : $event->online_urls,
			$request->has_param( 'status' ) ? sanitize_key( (string) $request['status'] ) : $event->status
		);
	}

	/**
	 * Validate an event before saving.
	 *
	 * @param Event $event Event.
	 * @return WP_Error|null
	 */
	private function validate_event( Event $event ): ?WP_Error {
		if ( '' === $event->title ) {
			return new WP_Error( 'rest_event_invalid_title', __( 'An event title is required.', 're-events' ), [ 'status' => 400 ] );
		}

		if ( '' === $event->start_utc ) {
			return new WP_Error( 'rest_event_invalid_start', __( 'A valid start time is required.', 're-events' ), [ 'status' => 400 ] );
		}

		if ( '' !== $event->end_utc && $event->end_utc < $event->start_utc ) {
			return new WP_Error( 'rest_event_invalid_range', __( 'The end time must not be before the start time.', 're-events' ), [ 'status' => 400 ] );
		}

		if ( $event->venue_id && 'reevents_venue' !== get_post_type( $event->venue_id ) ) {
			return new WP_Error( 'rest_event_invalid_venue', __( 'The venue does not exist.', 're-events' ), [ 'status' => 400 ] );
		}

		foreach ( $event->organizer_ids as $organizer_id ) {
			if ( 'reevents_organizer' !== get_post_type( $organizer_id ) ) {
				return new WP_Error( 'rest_event_invalid_organizer', __( 'An organizer does not exist.', 're-events' ), [ 'status' => 400 ] );
			}
		}

		return null;
	}

	/**
	 * Validate a UTC datetime.
	 *
	 * @param mixed $value Raw value.
	 * @return true|WP_Error
	 */
	public static function validate_datetime( $value ) {
		$date = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', (string) $value, new DateTimeZone( 'UTC' ) );

		return $date instanceof DateTimeImmutable
			? true
			: new WP_Error( 'rest_event_invalid_datetime', __( 'Use the Y-m-d H:i:s format in UTC.', 're-events' ) );
	}

	/**
	 * Validate an IANA timezone.
	 *
	 * @param mixed $value Raw value.
	 * @return true|WP_Error
	 */
	public static function validate_timezone( $value ) {
		return in_array( (string) $value, timezone_identifiers_list(), true )
			? true
			: new WP_Error( 'rest_event_invalid_timezone', __( 'Use a valid IANA timezone.', 're-events' ) );
	}

	/**
	 * Validate a recurrence rule.
	 *
	 * @param mixed $value Raw value.
	 * @return true|WP_Error
	 */
	public static function validate_rrule( $value ) {
		$value = (string) $value;

		if ( '' === $value ) {
			return true;
		}

		try {
			new RRule( $value, new DateTime( 'now', new DateTimeZone( 'UTC' ) ) );
		} catch ( InvalidArgumentException ) {
			return new WP_Error( 'rest_event_invalid_rrule', __( 'The recurrence rule is invalid.', 're-events' ) );
		}

		return true;
	}

	/**
	 * Serialize an event.
	 *
	 * @param Event           $item    Event.
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $item, $request ) {
		return rest_ensure_response(
			[
				'id'            => $item->id,
				'title'         => $item->title,
				'start_utc'     => $item->start_utc,
				'end_utc'       => $item->end_utc,
				'timezone'      => $item->timezone,
				'all_day'       => $item->all_day,
				'rrule'         => $item->rrule,
				'exdates'       => $item->exdates,
				'rdates'        => $item->rdates,
				'timeslots'     => $item->timeslots,
				'venue_id'      => $item->venue_id,
				'organizer_ids' => $item->organizer_ids,
				'capacity'      => $item->capacity,
				'online_urls'   => $item->online_urls,
				'status'        => $item->status,
				'permalink'     => (string) get_permalink( $item->id ),
			]
		);
	}

	/**
	 * Collection parameters.
	 *
	 * @return array<string, mixed>
	 */
	public function get_collection_params() {
		$params = parent::get_collection_params();

		$params['search'] = [
			'description'       => __( 'Limit results to events matching a search term.', 're-events' ),
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
		];

		$params['category'] = [
			'description'       => __( 'Limit results to a category term ID.', 're-events' ),
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
		];

		$params['venue'] = [
			'description'       => __( 'Limit results to a venue ID.', 're-events' ),
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
		];

		$params['from'] = [
			'description'       => __( 'Only events starting on or after this date (Y-m-d, UTC).', 're-events' ),
			'type'              => 'string',
			'sanitize_callback' => [ self::class, 'sanitize_date' ],
		];

		$params['to'] = [
			'description'       => __( 'Only events starting on or before this date (Y-m-d, UTC).', 're-events' ),
			'type'              => 'string',
			'sanitize_callback' => [ self::class, 'sanitize_date' ],
		];

		$params['upcoming'] = [
			'description'       => __( 'Only include upcoming events.', 're-events' ),
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
		];

		return $params;
	}

	/**
	 * Writable event parameters.
	 *
	 * @param bool $required Whether title and start time are required.
	 * @return array<string, mixed>
	 */
	private function get_write_args( bool $required ): array {
		return [
			'title'         => [
				'description'       => __( 'Event title.', 're-events' ),
				'type'              => 'string',
				'required'          => $required,
				'sanitize_callback' => 'sanitize_text_field',
			],
			'start_utc'     => [
				'description'       => __( 'Start time in UTC (Y-m-d H:i:s).', 're-events' ),
				'type'              => 'string',
				'required'          => $required,
				'validate_callback' => [ self::class, 'validate_datetime' ],
				'sanitize_callback' => [ Meta::class, 'sanitize_datetime' ],
			],
			'end_utc'       => [
				'description'       => __( 'End time in UTC (Y-m-d H:i:s).', 're-events' ),
				'type'              => 'string',
				'validate_callback' => [ self::class, 'validate_datetime' ],
				'sanitize_callback' => [ Meta::class, 'sanitize_datetime' ],
			],
			'timezone'      => [
				'description'       => __( 'IANA timezone.', 're-events' ),
				'type'              => 'string',
				'validate_callback' => [ self::class, 'validate_timezone' ],
			],
			'all_day'       => [
				'description' => __( 'Whether the event lasts all day.', 're-events' ),
				'type'        => 'boolean',
			],
			'rrule'         => [
				'description'       => __( 'RFC 5545 recurrence rule.', 're-events' ),
				'type'              => 'string',
				'validate_callback' => [ self::class, 'validate_rrule' ],
				'sanitize_callback' => 'sanitize_text_field',
			],
			'exdates'       => [
				'description'       => __( 'Excluded occurrence dates in UTC (Y-m-d H:i:s).', 're-events' ),
				'type'              => 'array',
				'items'             => [ 'type' => 'string' ],
				'sanitize_callback' => [ Meta::class, 'sanitize_string_array' ],
			],
			'rdates'        => [
				'description'       => __( 'Additional occurrence dates in UTC (Y-m-d H:i:s).', 're-events' ),
				'type'              => 'array',
				'items'             => [ 'type' => 'string' ],
				'sanitize_callback' => [ Meta::class, 'sanitize_string_array' ],
			],
			'timeslots'     => [
				'description'       => __( 'Daily timeslots.', 're-events' ),
				'type'              => 'array',
				'items'             => [
					'type'       => 'object',
					'properties' => [
						'start'    => [ 'type' => 'string' ],
						'end'      => [ 'type' => 'string' ],
						'capacity' => [ 'type' => 'integer' ],
					],
				],
				'sanitize_callback' => [ Meta::class, 'sanitize_timeslots' ],
			],
			'venue_id'      => [
				'description'       => __( 'Venue post ID.', 're-events' ),
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			],
			'organizer_ids' => [
				'description'       => __( 'Organizer post IDs.', 're-events' ),
				'type'              => 'array',
				'items'             => [ 'type' => 'integer' ],
				'sanitize_callback' => [ Meta::class, 'sanitize_id_array' ],
			],
			'capacity'      => [
				'description'       => __( 'Event capacity.', 're-events' ),
				'type'              => 'integer',
				'minimum'           => 0,
				'sanitize_callback' => 'absint',
			],
			'online_urls'   => [
				'description'       => __( 'Online event links.', 're-events' ),
				'type'              => 'array',
				'items'             => [
					'type'       => 'object',
					'properties' => [
						'label' => [ 'type' => 'string' ],
						'url'   => [ 'type' => 'string' ],
					],
				],
				'sanitize_callback' => [ Meta::class, 'sanitize_urls' ],
			],
			'status'        => [
				'description' => __( 'Event status.', 're-events' ),
				'type'        => 'string',
				'enum'        => [ 'scheduled', 'cancelled' ],
			],
		];
	}

	/**
	 * Sanitize a Y-m-d date.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_date( $value ): string {
		$value = sanitize_text_field( (string) $value );
		$date  = DateTimeImmutable::createFromFormat( '!Y-m-d', $value, new DateTimeZone( 'UTC' ) );

		return $date instanceof DateTimeImmutable ? $date->format( 'Y-m-d' ) : '';
	}

	/**
	 * Item schema.
	 *
	 * @return array<string, mixed>
	 */
	public function get_item_schema() {
		return [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'event',
			'type'       => 'object',
			'properties' => [
				'id'            => [
					'description' => __( 'Unique identifier for the event.', 're-events' ),
					'type'        => 'integer',
					'readonly'    => true,
				],
				'title'         => [
					'description' => __( 'Event title.', 're-events' ),
					'type'        => 'string',
				],
				'start_utc'     => [
					'description' => __( 'Start time in UTC.', 're-events' ),
					'type'        => 'string',
					'format'      => 'date-time',
				],
				'end_utc'       => [
					'description' => __( 'End time in UTC.', 're-events' ),
					'type'        => 'string',
					'format'      => 'date-time',
				],
				'timezone'      => [
					'description' => __( 'Event timezone.', 're-events' ),
					'type'        => 'string',
				],
				'all_day'       => [
					'description' => __( 'Whether the event lasts all day.', 're-events' ),
					'type'        => 'boolean',
				],
				'rrule'         => [
					'description' => __( 'Recurrence rule.', 're-events' ),
					'type'        => 'string',
				],
				'exdates'       => [
					'description' => __( 'Excluded occurrence dates.', 're-events' ),
					'type'        => 'array',
					'items'       => [ 'type' => 'string' ],
				],
				'rdates'        => [
					'description' => __( 'Additional occurrence dates.', 're-events' ),
					'type'        => 'array',
					'items'       => [ 'type' => 'string' ],
				],
				'timeslots'     => [
					'description' => __( 'Daily timeslots.', 're-events' ),
					'type'        => 'array',
					'items'       => [
						'type'       => 'object',
						'properties' => [
							'start'    => [ 'type' => 'string' ],
							'end'      => [ 'type' => 'string' ],
							'capacity' => [ 'type' => 'integer' ],
						],
					],
				],
				'venue_id'      => [
					'description' => __( 'Venue post ID.', 're-events' ),
					'type'        => 'integer',
				],
				'organizer_ids' => [
					'description' => __( 'Organizer post IDs.', 're-events' ),
					'type'        => 'array',
					'items'       => [ 'type' => 'integer' ],
				],
				'capacity'      => [
					'description' => __( 'Event capacity.', 're-events' ),
					'type'        => 'integer',
				],
				'online_urls'   => [
					'description' => __( 'Online event links.', 're-events' ),
					'type'        => 'array',
					'items'       => [
						'type'       => 'object',
						'properties' => [
							'label' => [ 'type' => 'string' ],
							'url'   => [ 'type' => 'string' ],
						],
					],
				],
				'status'        => [
					'description' => __( 'Event status.', 're-events' ),
					'type'        => 'string',
				],
				'permalink'     => [
					'description' => __( 'Event permalink.', 're-events' ),
					'type'        => 'string',
					'format'      => 'uri',
				],
			],
		];
	}
}

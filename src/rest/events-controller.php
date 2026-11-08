<?php
/**
 * Events REST controller.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\REST;

use DateTimeImmutable;
use DateTimeZone;
use ReEvents\Data\Event_Repository;
use ReEvents\Data\Occurrence_Repository;
use ReEvents\Domain\Event;
use ReEvents\Query\Event_Query;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Read-only events endpoints.
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

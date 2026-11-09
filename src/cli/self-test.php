<?php
/**
 * ReEvents self-test.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\CLI;

use ReEvents\Data\Occurrence_Repository;
use ReEvents\Data\Ticket_Repository;
use ReEvents\Domain\Event;
use ReEvents\Query\Event_Query;
use ReEvents\Services\Occurrence_Service;
use WP_CLI;
use WP_REST_Request;

/**
 * Runs focused checks against recurrence, queries and the REST API.
 */
final class Self_Test {

	/**
	 * Run the checks.
	 */
	public static function run(): void {
		$failures = [];

		self::check_expansion( $failures );
		self::check_timeslots( $failures );
		self::check_upcoming_query( $failures );
		self::check_availability( $failures );
		self::check_rest( $failures );

		if ( $failures ) {
			WP_CLI::error( implode( "\n", $failures ) );
		}

		WP_CLI::success( 'ReEvents self-test passed.' );
	}

	/**
	 * Check that an old weekly event still has future occurrences.
	 *
	 * @param string[] $failures Failures.
	 */
	private static function check_expansion( array &$failures ): void {
		$start = gmdate( 'Y-m-d H:i:s', strtotime( '-2 years' ) );
		$end   = gmdate( 'Y-m-d H:i:s', strtotime( '-2 years +1 hour' ) );
		$event = new Event( 0, 'Self test weekly', $start, $end, 'UTC', false, 'FREQ=WEEKLY' );
		$rows  = Occurrence_Service::expand( $event );
		$found = false;

		foreach ( $rows as $row ) {
			if ( strtotime( $row['start_utc'] ) > time() ) {
				$found = true;
				break;
			}
		}

		self::assert( $found, 'Weekly expansion has no future occurrences.', $failures );
	}

	/**
	 * Check that timeslots produce one row per slot.
	 *
	 * @param string[] $failures Failures.
	 */
	private static function check_timeslots( array &$failures ): void {
		$start = gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) );
		$event = new Event(
			0,
			'Self test slots',
			$start,
			gmdate( 'Y-m-d H:i:s', strtotime( '+1 day +2 hours' ) ),
			'UTC',
			false,
			'',
			[],
			[],
			[
				[
					'start'    => '10:00',
					'end'      => '11:00',
					'capacity' => 5,
				],
			]
		);
		$rows  = Occurrence_Service::expand( $event );

		self::assert( isset( $rows[0] ) && '10:00-11:00' === $rows[0]['timeslot_key'], 'Timeslot expansion failed.', $failures );
	}

	/**
	 * Check that an old recurring event is returned by the upcoming query.
	 *
	 * @param string[] $failures Failures.
	 */
	private static function check_upcoming_query( array &$failures ): void {
		$start = gmdate( 'Y-m-d H:i:s', strtotime( '-2 years' ) );
		$end   = gmdate( 'Y-m-d H:i:s', strtotime( '-2 years +1 hour' ) );
		$id    = wp_insert_post(
			[
				'post_type'   => 'reevents_event',
				'post_status' => 'publish',
				'post_title'  => 'Self test recurring',
				'meta_input'  => [
					'_reevents_start_utc' => $start,
					'_reevents_end_utc'   => $end,
					'_reevents_timezone'  => 'UTC',
					'_reevents_rrule'     => 'FREQ=WEEKLY',
					'_reevents_status'    => 'scheduled',
				],
			]
		);

		if ( ! $id ) {
			$failures[] = 'Could not create a test event.';
			return;
		}

		Occurrence_Service::sync( (int) $id );
		$result = Event_Query::query(
			[
				'upcoming' => true,
				'per_page' => 100,
			]
		);

		self::assert( in_array( (int) $id, $result['ids'], true ), 'Upcoming query missed a recurring event.', $failures );

		wp_update_post(
			[
				'ID'          => (int) $id,
				'post_status' => 'draft',
			]
		);

		self::assert( 0 === ( new Occurrence_Repository() )->count_for_event( (int) $id ), 'Draft event kept occurrences.', $failures );

		wp_delete_post( (int) $id, true );

		self::assert( 0 === ( new Occurrence_Repository() )->count_for_event( (int) $id ), 'Deleting an event left occurrences behind.', $failures );
	}

	/**
	 * Check that ticket capacity is reserved atomically.
	 *
	 * @param string[] $failures Failures.
	 */
	private static function check_availability( array &$failures ): void {
		$event_id = wp_insert_post(
			[
				'post_type'   => 'reevents_event',
				'post_status' => 'publish',
				'post_title'  => 'Self test capacity',
			]
		);

		if ( ! $event_id ) {
			$failures[] = 'Could not create a capacity test event.';
			return;
		}

		$repo      = new Ticket_Repository();
		$ticket_id = $repo->save(
			[
				'event_id' => (int) $event_id,
				'name'     => 'Capacity ticket',
				'capacity' => 1,
			]
		);

		if ( is_wp_error( $ticket_id ) ) {
			wp_delete_post( (int) $event_id, true );
			$failures[] = 'Could not create a capacity test ticket.';
			return;
		}

		self::assert( $repo->reserve( (int) $ticket_id, 1 ), 'First seat was not reserved.', $failures );
		self::assert( ! $repo->reserve( (int) $ticket_id, 1 ), 'Capacity was exceeded.', $failures );

		$repo->release( (int) $ticket_id, 1 );

		self::assert( $repo->reserve( (int) $ticket_id, 1 ), 'Released seat was not reserved again.', $failures );

		$repo->delete( (int) $ticket_id );
		wp_delete_post( (int) $event_id, true );
	}

	/**
	 * Check create, update and delete through the REST API.
	 *
	 * @param string[] $failures Failures.
	 */
	private static function check_rest( array &$failures ): void {
		wp_set_current_user( 1 );

		$create = new WP_REST_Request( 'POST', '/re-events/v1/events' );
		$create->set_header( 'Content-Type', 'application/json' );
		$create->set_body(
			(string) wp_json_encode(
				[
					'title'     => 'Self test REST',
					'start_utc' => gmdate( 'Y-m-d H:i:s', strtotime( '+7 days' ) ),
					'end_utc'   => gmdate( 'Y-m-d H:i:s', strtotime( '+7 days +1 hour' ) ),
					'timezone'  => 'UTC',
				]
			)
		);
		$response = rest_do_request( $create );

		if ( 201 !== $response->get_status() ) {
			$failures[] = 'REST create did not return 201.';
			return;
		}

		$data = (array) $response->get_data();
		$id   = (int) ( $data['id'] ?? 0 );

		if ( ! $id ) {
			$failures[] = 'REST create returned no event ID.';
			return;
		}

		self::assert( ( new Occurrence_Repository() )->count_for_event( $id ) > 0, 'REST create did not build occurrences.', $failures );

		$update = new WP_REST_Request( 'PATCH', '/re-events/v1/events/' . $id );
		$update->set_header( 'Content-Type', 'application/json' );
		$update->set_body( (string) wp_json_encode( [ 'title' => 'Self test REST updated' ] ) );
		$response = rest_do_request( $update );

		self::assert( 200 === $response->get_status(), 'REST update did not return 200.', $failures );

		$delete   = new WP_REST_Request( 'DELETE', '/re-events/v1/events/' . $id );
		$response = rest_do_request( $delete );

		self::assert( 200 === $response->get_status(), 'REST delete did not return 200.', $failures );

		$get      = new WP_REST_Request( 'GET', '/re-events/v1/events/' . $id );
		$response = rest_do_request( $get );

		self::assert( 404 === $response->get_status(), 'Deleted event still resolves.', $failures );

		if ( get_post( $id ) ) {
			wp_delete_post( $id, true );
		}

		self::check_rest_resource( 'venues', [ 'title' => 'Self test venue' ], $failures );
		self::check_rest_resource( 'organizers', [ 'title' => 'Self test organizer' ], $failures );
		self::check_rest_resource( 'categories', [ 'name' => 'Self test category' ], $failures );

		$settings = new WP_REST_Request( 'GET', '/re-events/v1/settings' );
		$response = rest_do_request( $settings );

		self::assert( 200 === $response->get_status(), 'Settings read failed.', $failures );
	}

	/**
	 * Check create and delete for a simple REST resource.
	 *
	 * @param string               $base     REST base.
	 * @param array<string, mixed> $payload  Create payload.
	 * @param string[]             $failures Failures.
	 */
	private static function check_rest_resource( string $base, array $payload, array &$failures ): void {
		$create = new WP_REST_Request( 'POST', '/re-events/v1/' . $base );
		$create->set_header( 'Content-Type', 'application/json' );
		$create->set_body( (string) wp_json_encode( $payload ) );
		$response = rest_do_request( $create );

		if ( 201 !== $response->get_status() ) {
			$failures[] = 'REST create failed for ' . $base . '.';
			return;
		}

		$data = (array) $response->get_data();
		$id   = (int) ( $data['id'] ?? 0 );

		if ( ! $id ) {
			$failures[] = 'REST create returned no ID for ' . $base . '.';
			return;
		}

		$delete   = new WP_REST_Request( 'DELETE', '/re-events/v1/' . $base . '/' . $id );
		$response = rest_do_request( $delete );

		self::assert( 200 === $response->get_status(), 'REST delete failed for ' . $base . '.', $failures );

		$get      = new WP_REST_Request( 'GET', '/re-events/v1/' . $base . '/' . $id );
		$response = rest_do_request( $get );

		self::assert( 404 === $response->get_status(), 'Deleted ' . $base . ' still resolves.', $failures );

		if ( get_post( $id ) ) {
			wp_delete_post( $id, true );
		}
	}

	/**
	 * Record a failure.
	 *
	 * @param bool     $condition Condition.
	 * @param string   $message   Failure message.
	 * @param string[] $failures  Failures.
	 */
	private static function assert( bool $condition, string $message, array &$failures ): void {
		if ( ! $condition ) {
			$failures[] = $message;
		}
	}
}

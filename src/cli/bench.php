<?php
/**
 * Performance benchmark.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\CLI;

use ReEvents\Data\Booking_Repository;
use ReEvents\Data\Event_Repository;
use ReEvents\Data\Occurrence_Repository;
use ReEvents\Query\Event_Query;
use ReEvents\Services\Occurrence_Service;
use WP_CLI;
use WP_REST_Request;

/**
 * Seeds fixtures and measures the hot paths.
 */
final class Bench {

	/**
	 * Seed benchmark fixtures.
	 *
	 * ## OPTIONS
	 *
	 * [--events=<count>]
	 * : Number of events. Default 1000.
	 *
	 * [--occurrences=<count>]
	 * : Number of occurrences. Default 10000.
	 *
	 * [--bookings=<count>]
	 * : Number of bookings and attendees. Default 500.
	 *
	 * [--venues=<count>]
	 * : Number of venues. Default 50.
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Arguments.
	 */
	public function seed( array $args, array $assoc_args ): void {
		global $wpdb;

		$events      = max( 1, (int) ( $assoc_args['events'] ?? 1000 ) );
		$occurrences = max( 1, (int) ( $assoc_args['occurrences'] ?? 10000 ) );
		$bookings    = max( 1, (int) ( $assoc_args['bookings'] ?? 500 ) );
		$venues      = max( 1, (int) ( $assoc_args['venues'] ?? 50 ) );

		$venue_ids = [];

		for ( $i = 0; $i < $venues; $i++ ) {
			$venue_ids[] = (int) wp_insert_post(
				[
					'post_type'   => 'reevents_venue',
					'post_status' => 'publish',
					'post_title'  => 'Bench Venue ' . $i,
					'meta_input'  => [ '_reevents_bench' => 1 ],
				]
			);
		}

		$event_ids = [];

		for ( $i = 0; $i < $events; $i++ ) {
			$start = gmdate( 'Y-m-d H:i:s', strtotime( '+' . $i . ' hours' ) );
			$end   = gmdate( 'Y-m-d H:i:s', strtotime( '+' . $i . ' hours +1 hour' ) );

			$event_ids[] = (int) wp_insert_post(
				[
					'post_type'   => 'reevents_event',
					'post_status' => 'publish',
					'post_title'  => 'Bench Event ' . $i,
					'meta_input'  => [
						'_reevents_start_utc' => $start,
						'_reevents_end_utc'   => $end,
						'_reevents_timezone'  => 'UTC',
						'_reevents_status'    => 'scheduled',
						'_reevents_venue_id'  => $venue_ids[ $i % $venues ],
						'_reevents_bench'     => 1,
					],
				]
			);
		}

		$table = $wpdb->prefix . 'reevents_occurrences';
		$batch = [];

		for ( $i = 0; $i < $occurrences; $i++ ) {
			$start   = gmdate( 'Y-m-d H:i:s', strtotime( '+' . $i . ' hours' ) );
			$end     = gmdate( 'Y-m-d H:i:s', strtotime( '+' . $i . ' hours +1 hour' ) );
			$batch[] = $wpdb->prepare(
				'(%d,%s,%s,%s,%s,%d,%s)',
				$event_ids[ $i % $events ],
				$start,
				$end,
				'UTC',
				'',
				100,
				'scheduled'
			);

			if ( count( $batch ) >= 500 ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Benchmark fixture.
				$wpdb->query( "INSERT IGNORE INTO {$table} (event_id,start_utc,end_utc,timezone,timeslot_key,capacity,status) VALUES " . implode( ',', $batch ) );
				$batch = [];
			}
		}

		if ( $batch ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Benchmark fixture.
			$wpdb->query( "INSERT IGNORE INTO {$table} (event_id,start_utc,end_utc,timezone,timeslot_key,capacity,status) VALUES " . implode( ',', $batch ) );
		}

		$booking_ids = [];

		for ( $i = 0; $i < $bookings; $i++ ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Benchmark fixture.
			$result = $wpdb->insert(
				$wpdb->prefix . 'reevents_bookings',
				[
					'reference'     => 'BENCH-' . $i,
					'event_id'      => $event_ids[ $i % $events ],
					'occurrence_id' => 0,
					'ticket_id'     => 0,
					'user_id'       => 0,
					'quantity'      => 1,
					'status'        => 'confirmed',
					'total_minor'   => 0,
					'currency'      => 'USD',
					'gateway'       => '',
					'gateway_ref'   => '',
					'created_utc'   => gmdate( 'Y-m-d H:i:s', strtotime( '-' . $i . ' hours' ) ),
					'updated_utc'   => gmdate( 'Y-m-d H:i:s' ),
				],
				[ '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s' ]
			);

			if ( $result ) {
				$booking_ids[] = (int) $wpdb->insert_id;
			}
		}

		foreach ( $booking_ids as $i => $booking_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Benchmark fixture.
			$wpdb->insert(
				$wpdb->prefix . 'reevents_attendees',
				[
					'booking_id'  => $booking_id,
					'ticket_id'   => 0,
					'name'        => 'Bench Attendee ' . $i,
					'email'       => 'bench' . $i . '@example.com',
					'fields_json' => '[]',
					'checked_in'  => 0,
				],
				[ '%d', '%d', '%s', '%s', '%s', '%d' ]
			);
		}

		WP_CLI::success( sprintf( 'Seeded %d events, %d occurrences, %d bookings.', $events, $occurrences, count( $booking_ids ) ) );
	}

	/**
	 * Run the benchmark.
	 *
	 * ## OPTIONS
	 *
	 * [--iterations=<count>]
	 * : Runs per case. Default 5.
	 *
	 * [--warm]
	 * : Keep the object cache between runs.
	 *
	 * [--assert]
	 * : Fail when a budget is exceeded.
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Arguments.
	 */
	public function run( array $args, array $assoc_args ): void {
		global $wpdb;

		$iterations = max( 1, (int) ( $assoc_args['iterations'] ?? 5 ) );
		$warm       = ! empty( $assoc_args['warm'] );
		$assert     = ! empty( $assoc_args['assert'] );
		$ids        = self::bench_event_ids();

		if ( ! $ids ) {
			WP_CLI::error( 'No benchmark events found. Run `wp re-events bench seed` first.' );
		}

		$sync_event = Test_Factory::event(
			[
				'title' => 'Bench sync event',
				'meta'  => [
					'_reevents_start_utc' => gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
					'_reevents_end_utc'   => gmdate( 'Y-m-d H:i:s', strtotime( '-1 day +1 hour' ) ),
					'_reevents_timezone'  => 'UTC',
					'_reevents_rrule'     => 'FREQ=DAILY',
					'_reevents_timeslots' => [
						[
							'start'    => '10:00',
							'end'      => '11:00',
							'capacity' => 10,
						],
						[
							'start'    => '20:00',
							'end'      => '21:00',
							'capacity' => 10,
						],
					],
					'_reevents_bench'     => 1,
				],
			]
		);

		$cases = [
			'event_list'        => [
				'budget_ms' => 500,
				'budget_q'  => 40,
				'run'       => static fn() => Event_Query::query(
					[
						'upcoming' => true,
						'per_page' => 100,
					]
				),
			],
			'event_search'      => [
				'budget_ms' => 500,
				'budget_q'  => 40,
				'run'       => static fn() => Event_Query::query(
					[
						'search'   => 'Bench',
						'per_page' => 100,
					]
				),
			],
			'event_range'       => [
				'budget_ms' => 500,
				'budget_q'  => 40,
				'run'       => static fn() => Event_Query::query(
					[
						'from'     => gmdate( 'Y-m-d' ),
						'to'       => gmdate( 'Y-m-d', strtotime( '+30 days' ) ),
						'per_page' => 100,
					]
				),
			],
			'event_hydrate'     => [
				'budget_ms' => 300,
				'budget_q'  => 10,
				'run'       => static fn() => ( new Event_Repository() )->find_many( array_slice( $ids, 0, 100 ) ),
			],
			'occurrences_range' => [
				'budget_ms' => 300,
				'budget_q'  => 5,
				'run'       => static fn() => ( new Occurrence_Repository() )->get_between( gmdate( 'Y-m-d H:i:s' ), gmdate( 'Y-m-d H:i:s', strtotime( '+30 days' ) ) ),
			],
			'attendees'         => [
				'budget_ms' => 300,
				'budget_q'  => 10,
				'run'       => static fn() => ( new Booking_Repository() )->attendees_query( [ 'limit' => 20 ] ),
			],
			'bookings'          => [
				'budget_ms' => 300,
				'budget_q'  => 10,
				'run'       => static fn() => ( new Booking_Repository() )->query( [ 'limit' => 20 ] ),
			],
			'rest_events'       => [
				'budget_ms' => 600,
				'budget_q'  => 50,
				'run'       => static function () {
					$request = new WP_REST_Request( 'GET', '/re-events/v1/events' );
					$request->set_query_params( [ 'per_page' => 100 ] );

					return rest_do_request( $request );
				},
			],
			'render_list'       => [
				'budget_ms' => 600,
				'budget_q'  => 50,
				'run'       => static function () {
					ob_start();
					do_shortcode( '[reevents_events limit="10" upcoming="1"]' );

					return ob_get_clean();
				},
			],
			'sync_500'          => [
				'budget_ms' => 2000,
				'budget_q'  => 20,
				'run'       => static fn() => Occurrence_Service::sync( $sync_event ),
			],
		];

		$failures = [];

		WP_CLI::line( sprintf( '%-18s %10s %8s', 'case', 'median ms', 'queries' ) );

		foreach ( $cases as $name => $case ) {
			$times   = [];
			$queries = 0;

			for ( $i = 0; $i < $iterations; $i++ ) {
				if ( ! $warm ) {
					wp_cache_flush();
				}

				$before = $wpdb->num_queries;
				$start  = microtime( true );

				$case['run']();

				$times[] = ( microtime( true ) - $start ) * 1000;
				$queries = max( $queries, $wpdb->num_queries - $before );
			}

			sort( $times );
			$median = $times[ (int) floor( count( $times ) / 2 ) ];

			WP_CLI::line( sprintf( '%-18s %10.1f %8d', $name, $median, $queries ) );

			if ( $assert && ( $median > $case['budget_ms'] || $queries > $case['budget_q'] ) ) {
				$failures[] = sprintf( '%s: %.1f ms, %d queries', $name, $median, $queries );
			}
		}

		wp_delete_post( $sync_event, true );

		if ( $failures ) {
			WP_CLI::error( "Budget exceeded:\n" . implode( "\n", $failures ) );
		}

		WP_CLI::success( 'Benchmark complete.' );
	}

	/**
	 * Remove benchmark fixtures.
	 */
	public function clean(): void {
		global $wpdb;

		$ids = self::bench_event_ids();

		if ( ! $ids ) {
			WP_CLI::success( 'No benchmark fixtures found.' );
			return;
		}

		foreach ( array_chunk( $ids, 500 ) as $chunk ) {
			$in = implode( ',', array_map( 'intval', $chunk ) );

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Benchmark cleanup.
			$wpdb->query( "DELETE FROM {$wpdb->prefix}reevents_attendees WHERE booking_id IN (SELECT id FROM {$wpdb->prefix}reevents_bookings WHERE event_id IN ({$in}))" );
			$wpdb->query( "DELETE FROM {$wpdb->prefix}reevents_bookings WHERE event_id IN ({$in})" );
			$wpdb->query( "DELETE FROM {$wpdb->prefix}reevents_occurrences WHERE event_id IN ({$in})" );
			$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE post_id IN ({$in})" );
			$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE ID IN ({$in})" );
			// phpcs:enable
		}

		wp_cache_flush();
		WP_CLI::success( sprintf( 'Removed %d benchmark events.', count( $ids ) ) );
	}

	/**
	 * Benchmark event IDs.
	 *
	 * @return int[]
	 */
	private static function bench_event_ids(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Benchmark fixture lookup.
		$ids = $wpdb->get_col( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_reevents_bench' AND meta_value = '1'" );

		return is_array( $ids ) ? array_map( 'intval', $ids ) : [];
	}
}

<?php
/**
 * Occurrence sync integration tests.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Tests\Integration;

use ReEvents\CLI\Test_Factory;
use ReEvents\Data\Occurrence_Repository;
use ReEvents\Services\Occurrence_Service;
use WP_UnitTestCase;

/**
 * Covers occurrence replacement.
 */
final class Occurrence_Test extends WP_UnitTestCase {

	/**
	 * Removing a timeslot removes every stale occurrence, even past 1,000.
	 */
	public function test_large_event_drops_stale_occurrences(): void {
		$event = Test_Factory::event(
			[
				'title' => 'Large occurrence event',
				'meta'  => [
					'_reevents_start_utc' => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) ),
					'_reevents_end_utc'   => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day +3 hours' ) ),
					'_reevents_timezone'  => 'UTC',
					'_reevents_status'    => 'scheduled',
					'_reevents_rrule'     => 'FREQ=DAILY;COUNT=360',
					'_reevents_timeslots' => [
						[
							'start'    => '10:00',
							'end'      => '11:00',
							'capacity' => 10,
						],
						[
							'start'    => '12:00',
							'end'      => '13:00',
							'capacity' => 10,
						],
						[
							'start'    => '14:00',
							'end'      => '15:00',
							'capacity' => 10,
						],
					],
				],
			]
		);

		Occurrence_Service::sync( $event );

		self::assertSame( 1080, ( new Occurrence_Repository() )->count_for_event( $event ) );

		update_post_meta(
			$event,
			'_reevents_timeslots',
			[
				[
					'start'    => '10:00',
					'end'      => '11:00',
					'capacity' => 10,
				],
				[
					'start'    => '12:00',
					'end'      => '13:00',
					'capacity' => 10,
				],
			]
		);

		Occurrence_Service::sync( $event );

		self::assertSame( 720, ( new Occurrence_Repository() )->count_for_event( $event ) );

		( new Occurrence_Repository() )->delete_for_event( $event );
		wp_delete_post( $event, true );
	}
}

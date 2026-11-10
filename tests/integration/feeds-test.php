<?php
/**
 * Feed integration tests.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Tests\Integration;

use ReEvents\CLI\Test_Factory;
use ReEvents\Frontend\Feeds;
use ReEvents\Frontend\Structured_Data;
use ReEvents\Services\Occurrence_Service;
use WP_UnitTestCase;

/**
 * Covers iCal and JSON-LD output.
 */
final class Feeds_Test extends WP_UnitTestCase {

	/**
	 * An event with an occurrence and a ticket.
	 */
	private function event(): int {
		$event = Test_Factory::event(
			[
				'title' => 'Feed event',
				'meta'  => [
					'_reevents_start_utc' => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) ),
					'_reevents_end_utc'   => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day +1 hour' ) ),
					'_reevents_timezone'  => 'UTC',
					'_reevents_status'    => 'scheduled',
				],
			]
		);

		Test_Factory::ticket( $event, [ 'price_minor' => 500 ] );
		Occurrence_Service::sync( $event );

		return $event;
	}

	/**
	 * A single-event feed contains a VEVENT.
	 */
	public function test_single_event_ical(): void {
		$event = $this->event();
		$ical  = Feeds::calendar( (string) $event );

		self::assertIsString( $ical );
		self::assertStringContainsString( 'BEGIN:VCALENDAR', $ical );
		self::assertStringContainsString( 'BEGIN:VEVENT', $ical );
		self::assertStringContainsString( 'SUMMARY:Feed event', $ical );
	}

	/**
	 * The all-events feed includes the event.
	 */
	public function test_all_events_ical(): void {
		$event = $this->event();
		$ical  = Feeds::calendar( 'all' );

		self::assertIsString( $ical );
		self::assertStringContainsString( 'UID:event-' . $event, $ical );
	}

	/**
	 * JSON-LD contains the Event fields and offers.
	 */
	public function test_structured_data(): void {
		$event = $this->event();
		$data  = Structured_Data::data( $event );

		self::assertSame( 'Event', $data['@type'] );
		self::assertSame( 'Feed event', $data['name'] );
		self::assertArrayHasKey( 'startDate', $data );
		self::assertArrayHasKey( 'offers', $data );
		self::assertSame( '5.00', $data['offers'][0]['price'] );
	}
}

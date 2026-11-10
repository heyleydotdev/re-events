<?php
/**
 * Feed integration tests.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Tests\Integration;

use ReEvents\CLI\Test_Factory;
use ReEvents\Data\Event_Repository;
use ReEvents\Frontend\Feeds;
use ReEvents\Frontend\Structured_Data;
use ReEvents\Render\Calendar_Links;
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
	 * Calendar links cover the four providers.
	 */
	public function test_calendar_links(): void {
		$id     = $this->event();
		$event  = ( new Event_Repository() )->find( $id );
		$links  = Calendar_Links::links( $event, [] );
		$labels = array_column( $links, 'label' );

		self::assertCount( 4, $links );
		self::assertContains( 'Google', $labels );
		self::assertStringContainsString( 'calendar.google.com', $links[0]['url'] );
		self::assertStringContainsString( 'reevents_ical', $links[3]['url'] );
	}

	/**
	 * JSON-LD strips markup that could close the script tag.
	 */
	public function test_structured_data_escapes_markup(): void {
		$id   = Test_Factory::event(
			[
				'title' => 'Hello </script><script>alert(1)</script> World',
				'meta'  => [
					'_reevents_start_utc' => '2027-01-01 10:00:00',
					'_reevents_end_utc'   => '2027-01-01 11:00:00',
					'_reevents_timezone'  => 'UTC',
					'_reevents_status'    => 'scheduled',
				],
			]
		);
		$data = Structured_Data::data( $id );

		self::assertStringNotContainsString( '</script>', $data['name'] );
		self::assertStringNotContainsString( '<script>', $data['name'] );
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

<?php
/**
 * Calendar rendering tests.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Tests\Integration;

use ReEvents\CLI\Test_Factory;
use ReEvents\Render\Calendar;
use ReEvents\Services\Occurrence_Service;
use WP_UnitTestCase;

/**
 * Covers the calendar renderer.
 */
final class Calendar_Test extends WP_UnitTestCase {

	/**
	 * Create a June 2027 event.
	 *
	 * @param string               $title Event title.
	 * @param array<string, mixed> $meta  Extra meta.
	 */
	private function event( string $title, array $meta = [] ): int {
		$id = Test_Factory::event(
			[
				'title' => $title,
				'meta'  => array_merge(
					[
						'_reevents_start_utc' => '2027-06-15 10:00:00',
						'_reevents_end_utc'   => '2027-06-15 11:00:00',
						'_reevents_timezone'  => 'UTC',
						'_reevents_status'    => 'scheduled',
					],
					$meta
				),
			]
		);

		Occurrence_Service::sync( $id );

		return $id;
	}

	/**
	 * The month and time formats apply.
	 */
	public function test_formats(): void {
		$this->event( 'Calendar format event' );

		$html = Calendar::render(
			[
				'month'        => '2027-06',
				'month_format' => 'Y-m',
				'time_format'  => 'H:i',
			]
		);

		self::assertStringContainsString( '2027-06', $html );
		self::assertStringContainsString( 'Calendar format event', $html );
		self::assertStringContainsString( '10:00', $html );
	}

	/**
	 * Cancelled events are opt-in and carry a style hook.
	 */
	public function test_cancelled_events(): void {
		$this->event(
			'Calendar cancelled event',
			[ '_reevents_status' => 'cancelled' ]
		);

		$hidden = Calendar::render( [ 'month' => '2027-06' ] );
		self::assertStringNotContainsString( 'Calendar cancelled event', $hidden );

		$shown = Calendar::render(
			[
				'month'          => '2027-06',
				'show_cancelled' => true,
			]
		);

		self::assertStringContainsString( 'Calendar cancelled event', $shown );
		self::assertStringContainsString( 'is-cancelled', $shown );
	}

	/**
	 * Category filters narrow the month.
	 */
	public function test_category_filter(): void {
		$term = wp_insert_term( 'Calendar category', 'reevents_event_category' );

		if ( is_wp_error( $term ) ) {
			self::fail( 'Category could not be created.' );
		}

		$inside = $this->event( 'Calendar inside event' );
		$this->event( 'Calendar outside event' );

		wp_set_object_terms( $inside, [ (int) $term['term_id'] ], 'reevents_event_category' );

		$html = Calendar::render(
			[
				'month'    => '2027-06',
				'category' => (int) $term['term_id'],
			]
		);

		self::assertStringContainsString( 'Calendar inside event', $html );
		self::assertStringNotContainsString( 'Calendar outside event', $html );
	}

	/**
	 * Custom weekday labels keep the order the site supplied.
	 */
	public function test_custom_weekdays(): void {
		$html = Calendar::render(
			[
				'week_start' => 'sun',
				'weekdays'   => 'Sunday,Monday,Tuesday,Wednesday,Thursday,Friday,Saturday',
			]
		);

		$sunday   = strpos( $html, '<th scope="col">Sunday</th>' );
		$monday   = strpos( $html, '<th scope="col">Monday</th>' );
		$saturday = strpos( $html, '<th scope="col">Saturday</th>' );

		self::assertNotFalse( $sunday );
		self::assertNotFalse( $monday );
		self::assertNotFalse( $saturday );
		self::assertTrue( $sunday < $monday );
		self::assertTrue( $monday < $saturday );
	}

	/**
	 * Week start and today marking change the grid.
	 */
	public function test_week_start_and_today(): void {
		$html = Calendar::render(
			[
				'week_start' => 'sun',
				'show_today' => true,
			]
		);

		$sunday = strpos( $html, '<th scope="col">Sun</th>' );
		$monday = strpos( $html, '<th scope="col">Mon</th>' );

		self::assertNotFalse( $sunday );
		self::assertNotFalse( $monday );
		self::assertTrue( $sunday < $monday );
		self::assertStringContainsString( 'is-today', $html );

		$plain = Calendar::render( [ 'show_today' => false ] );
		self::assertStringNotContainsString( 'is-today', $plain );
	}
}

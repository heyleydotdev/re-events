<?php
/**
 * Booking form rendering tests.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Tests\Integration;

use ReEvents\CLI\Test_Factory;
use ReEvents\Data\Occurrence_Repository;
use ReEvents\Data\Ticket_Repository;
use ReEvents\Render\Booking_Form;
use ReEvents\Services\Occurrence_Service;
use WP_UnitTestCase;

/**
 * Covers the booking form renderer.
 */
final class Booking_Form_Test extends WP_UnitTestCase {

	/**
	 * Create a bookable event.
	 *
	 * @param array<string, mixed> $meta Extra meta.
	 */
	private function event( array $meta = [] ): int {
		return Test_Factory::event(
			[
				'title' => 'Booking form event',
				'meta'  => array_merge(
					[
						'_reevents_start_utc' => gmdate( 'Y-m-d H:i:s', strtotime( '+2 days' ) ),
						'_reevents_end_utc'   => gmdate( 'Y-m-d H:i:s', strtotime( '+2 days +1 hour' ) ),
						'_reevents_timezone'  => 'UTC',
						'_reevents_status'    => 'scheduled',
					],
					$meta
				),
			]
		);
	}

	/**
	 * Ticket preselect, price toggle, quantity and heading apply.
	 */
	public function test_options(): void {
		$event  = $this->event();
		$first  = Test_Factory::ticket(
			$event,
			[
				'name'        => 'First ticket',
				'price_minor' => 1000,
			]
		);
		$second = Test_Factory::ticket(
			$event,
			[
				'name'        => 'Second ticket',
				'price_minor' => 2500,
			]
		);

		$html = Booking_Form::render(
			[
				'event'            => $event,
				'ticket'           => $second,
				'heading'          => 'Book a seat',
				'default_quantity' => 3,
				'max_quantity'     => 5,
				'show_price'       => true,
			]
		);

		self::assertStringContainsString( 'Book a seat', $html );
		self::assertStringContainsString( 'value="3"', $html );
		self::assertStringContainsString( 'max="5"', $html );
		self::assertStringContainsString( 'data-max-quantity="5"', $html );
		self::assertStringContainsString( '25.00 USD', $html );
		self::assertSame( 1, substr_count( $html, 'selected=' ) );
		self::assertMatchesRegularExpression( '/value="' . $second . '"\s+selected/', $html );
		self::assertDoesNotMatchRegularExpression( '/value="' . $first . '"\s+selected/', $html );
	}

	/**
	 * A ticket from another event is not preselected.
	 */
	public function test_cross_event_ticket(): void {
		$event = $this->event();
		$this->event();
		Test_Factory::ticket( $event, [ 'name' => 'Own ticket' ] );

		$other        = $this->event();
		$other_ticket = Test_Factory::ticket( $other, [ 'name' => 'Other ticket' ] );

		$html = Booking_Form::render(
			[
				'event'  => $event,
				'ticket' => $other_ticket,
			]
		);

		self::assertStringContainsString( 'Own ticket', $html );
		self::assertStringNotContainsString( 'Other ticket', $html );
		self::assertDoesNotMatchRegularExpression( '/value="' . $other_ticket . '"\s+selected/', $html );
	}

	/**
	 * Sold-out tickets can be hidden and prices can be omitted.
	 */
	public function test_sold_out_and_price(): void {
		$event = $this->event();
		$sold  = Test_Factory::ticket(
			$event,
			[
				'name'        => 'Sold ticket',
				'capacity'    => 1,
				'price_minor' => 500,
			]
		);
		Test_Factory::ticket(
			$event,
			[
				'name'        => 'Open ticket',
				'price_minor' => 1500,
			]
		);

		( new Ticket_Repository() )->reserve( $sold, 1 );

		$html = Booking_Form::render(
			[
				'event'         => $event,
				'hide_sold_out' => true,
				'show_price'    => false,
			]
		);

		self::assertStringNotContainsString( 'Sold ticket', $html );
		self::assertStringContainsString( 'Open ticket', $html );
		self::assertStringNotContainsString( 'USD', $html );
	}

	/**
	 * Occurrence preselect applies to the time selector.
	 */
	public function test_occurrence_preselect(): void {
		$event = $this->event(
			[
				'_reevents_timeslots' => [
					[
						'start'    => '10:00',
						'end'      => '11:00',
						'capacity' => 10,
					],
				],
			]
		);
		Test_Factory::ticket( $event, [ 'capacity' => 10 ] );
		Occurrence_Service::sync( $event );

		$occurrences = ( new Occurrence_Repository() )->get_for_event( $event );
		self::assertNotEmpty( $occurrences );

		$html = Booking_Form::render(
			[
				'event'      => $event,
				'occurrence' => (int) $occurrences[0]['id'],
			]
		);

		self::assertMatchesRegularExpression( '/value="' . (int) $occurrences[0]['id'] . '"\s+selected/', $html );
	}
}

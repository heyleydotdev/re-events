<?php
/**
 * Booking integration tests.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Tests\Integration;

use ReEvents\CLI\Test_Factory;
use ReEvents\Services\Booking_Service;
use WP_UnitTestCase;

/**
 * Covers capacity and seat release against the real database.
 */
final class Booking_Test extends WP_UnitTestCase {

	/**
	 * Prevent booking mail from leaving the test process.
	 */
	public function set_up(): void {
		parent::set_up();

		add_filter( 'pre_wp_mail', '__return_true' );
	}

	/**
	 * Capacity is not oversold.
	 */
	public function test_capacity_is_not_oversold(): void {
		$event  = Test_Factory::event( [ 'title' => 'Capacity test' ] );
		$ticket = Test_Factory::ticket( $event, [ 'capacity' => 2 ] );

		$first = Booking_Service::create(
			[
				'event_id'  => $event,
				'ticket_id' => $ticket,
				'quantity'  => 2,
			]
		);

		self::assertIsInt( $first );

		$second = Booking_Service::create(
			[
				'event_id'  => $event,
				'ticket_id' => $ticket,
				'quantity'  => 1,
			]
		);

		self::assertWPError( $second );
		self::assertSame( 'reevents_booking_sold_out', $second->get_error_code() );
	}

	/**
	 * Cancelling a booking releases its seats.
	 */
	public function test_cancel_releases_seats(): void {
		$event  = Test_Factory::event( [ 'title' => 'Cancel test' ] );
		$ticket = Test_Factory::ticket( $event, [ 'capacity' => 2 ] );

		$booking = Booking_Service::create(
			[
				'event_id'  => $event,
				'ticket_id' => $ticket,
				'quantity'  => 2,
			]
		);

		self::assertIsInt( $booking );
		self::assertTrue( Booking_Service::cancel( $booking ) );

		$again = Booking_Service::create(
			[
				'event_id'  => $event,
				'ticket_id' => $ticket,
				'quantity'  => 2,
			]
		);

		self::assertIsInt( $again );
	}

	/**
	 * Changing status to cancelled releases seats.
	 */
	public function test_status_change_releases_seats(): void {
		$event  = Test_Factory::event( [ 'title' => 'Status test' ] );
		$ticket = Test_Factory::ticket( $event, [ 'capacity' => 1 ] );

		$booking = Booking_Service::create(
			[
				'event_id'  => $event,
				'ticket_id' => $ticket,
				'quantity'  => 1,
			]
		);

		self::assertIsInt( $booking );
		self::assertTrue( Booking_Service::update_status( $booking, 'cancelled' ) );

		$again = Booking_Service::create(
			[
				'event_id'  => $event,
				'ticket_id' => $ticket,
				'quantity'  => 1,
			]
		);

		self::assertIsInt( $again );
	}
}

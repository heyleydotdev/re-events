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
	 * Cancelled events cannot be booked.
	 */
	public function test_cancelled_event_is_rejected(): void {
		$event  = Test_Factory::event( [ 'title' => 'Cancelled event test' ] );
		$ticket = Test_Factory::ticket( $event );

		update_post_meta( $event, '_reevents_status', 'cancelled' );

		$result = Booking_Service::create(
			[
				'event_id'  => $event,
				'ticket_id' => $ticket,
				'quantity'  => 1,
			]
		);

		self::assertWPError( $result );
		self::assertSame( 'reevents_booking_event_cancelled', $result->get_error_code() );
	}

	/**
	 * Per-slot booking is rejected until it is implemented.
	 */
	public function test_occurrence_id_is_rejected(): void {
		$event  = Test_Factory::event( [ 'title' => 'Occurrence test' ] );
		$ticket = Test_Factory::ticket( $event );

		$result = Booking_Service::create(
			[
				'event_id'      => $event,
				'ticket_id'     => $ticket,
				'occurrence_id' => 1,
				'quantity'      => 1,
			]
		);

		self::assertWPError( $result );
		self::assertSame( 'reevents_booking_invalid_occurrence', $result->get_error_code() );
	}

	/**
	 * Attendees cannot outnumber seats.
	 */
	public function test_more_attendees_than_seats_is_rejected(): void {
		$event  = Test_Factory::event( [ 'title' => 'Attendee count test' ] );
		$ticket = Test_Factory::ticket( $event, [ 'capacity' => 2 ] );

		$result = Booking_Service::create(
			[
				'event_id'  => $event,
				'ticket_id' => $ticket,
				'quantity'  => 1,
				'attendees' => [
					[
						'name'  => 'One',
						'email' => 'one@example.com',
					],
					[
						'name'  => 'Two',
						'email' => 'two@example.com',
					],
				],
			]
		);

		self::assertWPError( $result );
		self::assertSame( 'reevents_booking_too_many_attendees', $result->get_error_code() );
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

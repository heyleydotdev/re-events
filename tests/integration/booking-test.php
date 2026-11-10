<?php
/**
 * Booking integration tests.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Tests\Integration;

use ReEvents\CLI\Test_Factory;
use ReEvents\Data\Booking_Repository;
use ReEvents\Data\Event_Repository;
use ReEvents\Data\Occurrence_Repository;
use ReEvents\Data\Ticket_Repository;
use ReEvents\Services\Booking_Service;
use ReEvents\Services\Occurrence_Service;
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
	 * An unknown occurrence ID is rejected.
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
	 * A per-slot booking reserves the occurrence capacity.
	 */
	public function test_per_slot_booking(): void {
		$event  = Test_Factory::event(
			[
				'title' => 'Slot booking',
				'meta'  => [
					'_reevents_start_utc' => gmdate( 'Y-m-d H:i:s', strtotime( '+2 days' ) ),
					'_reevents_end_utc'   => gmdate( 'Y-m-d H:i:s', strtotime( '+2 days +2 hours' ) ),
					'_reevents_timezone'  => 'UTC',
					'_reevents_status'    => 'scheduled',
					'_reevents_timeslots' => [
						[
							'start'    => '10:00',
							'end'      => '11:00',
							'capacity' => 2,
						],
					],
				],
			]
		);
		$ticket = Test_Factory::ticket( $event );

		Occurrence_Service::sync( $event );

		$occurrences = ( new Occurrence_Repository() )->get_for_event( $event, 10 );
		$occurrence  = (int) $occurrences[0]['id'];

		$booking = Booking_Service::create(
			[
				'event_id'      => $event,
				'ticket_id'     => $ticket,
				'occurrence_id' => $occurrence,
				'quantity'      => 2,
			]
		);

		self::assertIsInt( $booking );
		self::assertSame( $occurrence, (int) ( new Booking_Repository() )->find( $booking )['occurrence_id'] );
		self::assertSame( 2, (int) ( new Occurrence_Repository() )->find( $occurrence )['sold'] );

		$sold_out = Booking_Service::create(
			[
				'event_id'      => $event,
				'ticket_id'     => $ticket,
				'occurrence_id' => $occurrence,
				'quantity'      => 1,
			]
		);

		self::assertWPError( $sold_out );
		self::assertSame( 'reevents_booking_sold_out', $sold_out->get_error_code() );
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

	/**
	 * An event with bookings cannot be deleted.
	 */
	public function test_event_with_booking_cannot_be_deleted(): void {
		$event  = Test_Factory::event( [ 'title' => 'Delete event test' ] );
		$ticket = Test_Factory::ticket( $event );

		self::assertIsInt(
			Booking_Service::create(
				[
					'event_id'  => $event,
					'ticket_id' => $ticket,
					'quantity'  => 1,
				]
			)
		);

		self::assertFalse( ( new Event_Repository() )->delete( $event ) );
	}

	/**
	 * A ticket with bookings cannot be deleted.
	 */
	public function test_ticket_with_booking_cannot_be_deleted(): void {
		$event  = Test_Factory::event( [ 'title' => 'Delete ticket test' ] );
		$ticket = Test_Factory::ticket( $event );

		self::assertIsInt(
			Booking_Service::create(
				[
					'event_id'  => $event,
					'ticket_id' => $ticket,
					'quantity'  => 1,
				]
			)
		);

		self::assertFalse( ( new Ticket_Repository() )->delete( $ticket ) );
	}
}

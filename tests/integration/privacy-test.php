<?php
/**
 * Privacy exporter and eraser tests.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Tests\Integration;

use ReEvents\CLI\Test_Factory;
use ReEvents\Data\Booking_Repository;
use ReEvents\Privacy;
use WP_UnitTestCase;

/**
 * Covers attendee export and erasure by email.
 */
final class Privacy_Test extends WP_UnitTestCase {

	/**
	 * Event used by the fixture.
	 *
	 * @var int
	 */
	private int $event = 0;

	/**
	 * Ticket used by the fixture.
	 *
	 * @var int
	 */
	private int $ticket = 0;

	/**
	 * Create an event and ticket.
	 */
	public function set_up(): void {
		parent::set_up();

		\ReEvents\Capabilities::add();
		delete_option( 'reevents_settings' );

		$this->event  = Test_Factory::event(
			[
				'title' => 'Privacy event',
				'meta'  => [
					'_reevents_start_utc' => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) ),
					'_reevents_end_utc'   => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day +1 hour' ) ),
					'_reevents_timezone'  => 'UTC',
				],
			]
		);
		$this->ticket = Test_Factory::ticket( $this->event );
	}

	/**
	 * Create a booking for an email.
	 *
	 * @param string $email Email.
	 * @param string $name  Name.
	 */
	private function booking( string $email, string $name ): int {
		return Test_Factory::booking(
			$this->event,
			$this->ticket,
			[
				'attendees' => [
					[
						'name'  => $name,
						'email' => $email,
					],
				],
			]
		);
	}

	/**
	 * Export returns only the exact email.
	 */
	public function test_export_matches_exact_email(): void {
		$this->booking( 'jane@example.com', 'Jane' );
		$this->booking( 'jane2@example.com', 'Other' );

		$result = Privacy::export( 'jane@example.com' );

		self::assertCount( 1, $result['data'] );
		self::assertSame( 'reevents-attendees', $result['data'][0]['group_id'] );

		$values = array_column( $result['data'][0]['data'], 'value' );

		self::assertContains( 'Jane', $values );
		self::assertContains( 'Privacy event', $values );
	}

	/**
	 * Erasure clears more than one page of attendees.
	 */
	public function test_erase_clears_all_pages(): void {
		$booking = $this->booking( 'bulk@example.com', 'Bulk' );
		$repo    = new Booking_Repository();

		for ( $i = 0; $i < 101; $i++ ) {
			$repo->add_attendee(
				$booking,
				[
					'name'  => 'Bulk ' . $i,
					'email' => 'bulk@example.com',
				]
			);
		}

		$first = Privacy::erase( 'bulk@example.com' );

		self::assertTrue( $first['items_removed'] );
		self::assertFalse( $first['done'] );
		self::assertCount( 2, $repo->attendees_by_email( 'bulk@example.com' ) );

		$second = Privacy::erase( 'bulk@example.com', 2 );

		self::assertTrue( $second['done'] );
		self::assertSame( [], $repo->attendees_by_email( 'bulk@example.com' ) );
	}

	/**
	 * Erase anonymizes attendees and keeps the booking.
	 */
	public function test_erase_keeps_booking(): void {
		$booking = $this->booking( 'erase@example.com', 'Erase Me' );

		$result = Privacy::erase( 'erase@example.com' );

		self::assertTrue( $result['items_removed'] );
		self::assertNotNull( ( new Booking_Repository() )->find( $booking ) );
		self::assertSame( [], Privacy::export( 'erase@example.com' )['data'] );

		foreach ( ( new Booking_Repository() )->attendees( $booking ) as $attendee ) {
			self::assertSame( '', $attendee['email'] );
			self::assertSame( '', $attendee['name'] );
		}
	}
}

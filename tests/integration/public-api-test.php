<?php
/**
 * Public API integration tests.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Tests\Integration;

use ReEvents\CLI\Test_Factory;
use ReEvents\Data\Venue_Repository;
use WP_UnitTestCase;
use WP_User;

/**
 * Covers the public PHP CRUD functions.
 */
final class Public_API_Test extends WP_UnitTestCase {

	/**
	 * Administrator with the plugin capabilities.
	 *
	 * @var int
	 */
	private int $admin = 0;

	/**
	 * Create an administrator.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$user        = new WP_User( $this->admin );

		foreach ( [ 'edit_reevents_events', 'publish_reevents_events', 'publish_reevents_venues', 'publish_reevents_organizers', 'reevents_manage_bookings', 'reevents_manage_settings' ] as $capability ) {
			$user->add_cap( $capability );
		}

		wp_set_current_user( $this->admin );
	}

	/**
	 * An event with a ticket and a booking.
	 *
	 * @return array{event:int,ticket:int,booking:int}
	 */
	private function fixture(): array {
		$event   = Test_Factory::event(
			[
				'title' => 'Public API event',
				'meta'  => [
					'_reevents_start_utc' => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) ),
					'_reevents_end_utc'   => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day +1 hour' ) ),
					'_reevents_timezone'  => 'UTC',
					'_reevents_status'    => 'scheduled',
				],
			]
		);
		$ticket  = Test_Factory::ticket( $event );
		$booking = Test_Factory::booking(
			$event,
			$ticket,
			[
				'attendees' => [
					[
						'name'  => 'API attendee',
						'email' => 'api@example.com',
					],
				],
			]
		);

		return [
			'event'   => $event,
			'ticket'  => $ticket,
			'booking' => $booking,
		];
	}

	/**
	 * Read and write events.
	 */
	public function test_event_crud(): void {
		$created = \reevents_create_event(
			[
				'title'     => 'API created event',
				'start_utc' => '2027-01-02 10:00:00',
				'end_utc'   => '2027-01-02 11:00:00',
				'timezone'  => 'UTC',
			]
		);

		self::assertIsArray( $created );
		self::assertSame( 'API created event', $created['title'] );
		self::assertNotSame( 0, $created['id'] );

		$updated = \reevents_update_event( (int) $created['id'], [ 'title' => 'API updated event' ] );

		self::assertIsArray( $updated );
		self::assertSame( 'API updated event', $updated['title'] );
		self::assertSame( $created['id'], $updated['id'] );

		$deleted = \reevents_delete_event( (int) $created['id'] );

		self::assertIsArray( $deleted );
		self::assertTrue( $deleted['deleted'] );
	}

	/**
	 * A supplied ID cannot turn a create into an update.
	 */
	public function test_create_ignores_supplied_id(): void {
		$fixture = $this->fixture();
		$created = \reevents_create_event(
			[
				'id'        => $fixture['event'],
				'title'     => 'API new event',
				'start_utc' => '2027-01-03 10:00:00',
				'end_utc'   => '2027-01-03 11:00:00',
				'timezone'  => 'UTC',
			]
		);

		self::assertIsArray( $created );
		self::assertNotSame( $fixture['event'], $created['id'] );
	}

	/**
	 * Draft events stay hidden from anonymous callers.
	 */
	public function test_draft_event_is_hidden(): void {
		$event = Test_Factory::event(
			[
				'title' => 'Draft API event',
				'meta'  => [
					'_reevents_start_utc' => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) ),
					'_reevents_end_utc'   => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day +1 hour' ) ),
					'_reevents_timezone'  => 'UTC',
				],
			]
		);

		wp_update_post(
			[
				'ID'          => $event,
				'post_status' => 'draft',
			]
		);

		wp_set_current_user( 0 );

		self::assertWPError( \reevents_get_event( $event ) );
	}

	/**
	 * Read helpers return the REST-like shapes.
	 */
	public function test_read_helpers(): void {
		$fixture = $this->fixture();

		self::assertSame( '1.0.0', \reevents_api_version() );
		self::assertSame( 'Public API event', \reevents_get_event( $fixture['event'] )['title'] );
		self::assertNotEmpty( \reevents_get_events( [ 'per_page' => 5 ] ) );
		self::assertSame( $fixture['ticket'], (int) \reevents_get_ticket( $fixture['ticket'] )['id'] );
		self::assertCount( 1, \reevents_get_event_tickets( $fixture['event'] ) );
	}

	/**
	 * Booking data requires booking access.
	 */
	public function test_booking_requires_access(): void {
		$fixture = $this->fixture();

		wp_set_current_user( 0 );

		self::assertWPError( \reevents_get_booking( $fixture['booking'] ) );
		self::assertWPError( \reevents_get_booking_attendees( $fixture['booking'] ) );

		wp_set_current_user( $this->admin );

		self::assertSame( $fixture['booking'], (int) \reevents_get_booking( $fixture['booking'] )['id'] );
		self::assertCount( 1, \reevents_get_booking_attendees( $fixture['booking'] ) );
	}

	/**
	 * Ticket, venue and organizer CRUD.
	 */
	public function test_resource_crud(): void {
		$fixture = $this->fixture();

		$ticket = \reevents_create_ticket(
			$fixture['event'],
			[
				'name'        => 'API ticket',
				'price_minor' => 500,
				'currency'    => 'USD',
			]
		);

		self::assertIsArray( $ticket );
		self::assertSame( 'API ticket', \reevents_update_ticket( (int) $ticket['id'], [ 'name' => 'API ticket updated' ] )['name'] );
		self::assertTrue( \reevents_delete_ticket( (int) $ticket['id'] )['deleted'] );

		$venue = \reevents_create_venue(
			[
				'title'   => 'API venue',
				'address' => '1 Main Street',
			]
		);

		self::assertIsArray( $venue );
		self::assertSame( 'API venue updated', \reevents_update_venue( (int) $venue['id'], [ 'title' => 'API venue updated' ] )['title'] );
		self::assertTrue( \reevents_delete_venue( (int) $venue['id'] )['deleted'] );

		$organizer = \reevents_create_organizer( [ 'title' => 'API organizer' ] );

		self::assertIsArray( $organizer );
		self::assertSame( 'API organizer updated', \reevents_update_organizer( (int) $organizer['id'], [ 'title' => 'API organizer updated' ] )['title'] );
		self::assertTrue( \reevents_delete_organizer( (int) $organizer['id'] )['deleted'] );
	}

	/**
	 * Category CRUD.
	 */
	public function test_category_crud(): void {
		$category = \reevents_create_category( [ 'name' => 'API category' ] );

		self::assertIsArray( $category );
		self::assertSame( 'API category updated', \reevents_update_category( (int) $category['id'], [ 'name' => 'API category updated' ] )['name'] );
		self::assertTrue( \reevents_delete_category( (int) $category['id'] )['deleted'] );
	}

	/**
	 * Booking creation and status update.
	 */
	public function test_booking_crud(): void {
		$fixture = $this->fixture();

		$booking = \reevents_create_booking(
			[
				'event_id'  => $fixture['event'],
				'ticket_id' => $fixture['ticket'],
				'quantity'  => 1,
				'attendees' => [
					[
						'name'  => 'API new attendee',
						'email' => 'new@example.com',
					],
				],
			]
		);

		self::assertIsArray( $booking );
		self::assertSame( 'confirmed', $booking['status'] );

		$updated = \reevents_update_booking_status( (int) $booking['id'], 'cancelled' );

		self::assertIsArray( $updated );
		self::assertSame( 'cancelled', $updated['status'] );
	}

	/**
	 * The same booking token returns the same booking.
	 */
	public function test_booking_token_is_idempotent(): void {
		$fixture = $this->fixture();
		$payload = [
			'event_id'      => $fixture['event'],
			'ticket_id'     => $fixture['ticket'],
			'quantity'      => 1,
			'attendees'     => [
				[
					'name'  => 'API retry attendee',
					'email' => 'retry@example.com',
				],
			],
			'booking_token' => 'api-token-' . wp_generate_uuid4(),
		];

		$first  = eevents_create_booking( $payload );
		$second = eevents_create_booking( $payload );

		self::assertIsArray( $first );
		self::assertIsArray( $second );
		self::assertSame( $first['id'], $second['id'] );
	}

	/**
	 * Occurrences, settings and venue query helpers.
	 */
	public function test_other_helpers(): void {
		$fixture = $this->fixture();
		$venue   = ( new Venue_Repository() )->save( [ 'title' => 'Helper venue' ] );
		\reevents_create_organizer( [ 'title' => 'Helper organizer' ] );

		self::assertIsArray( \reevents_sync_event_occurrences( $fixture['event'] ) );
		self::assertNotEmpty( \reevents_get_event_occurrences( $fixture['event'] ) );
		self::assertArrayHasKey( 'timezone', \reevents_get_settings() );
		self::assertNotEmpty( \reevents_get_venues() );
		self::assertNotEmpty( \reevents_get_organizers() );
		self::assertSame( 'Helper venue', \reevents_get_venue( (int) $venue )['title'] );
	}

	/**
	 * Published venue lookup still works through the repository helper.
	 */
	public function test_venue_repository_helper(): void {
		$id = ( new Venue_Repository() )->save( [ 'title' => 'Repository venue' ] );

		self::assertSame( 'Repository venue', \reevents_get_venue( (int) $id )['title'] );
	}
}

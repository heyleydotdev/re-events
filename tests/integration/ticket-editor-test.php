<?php
/**
 * Ticket editor tests.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Tests\Integration;

use ReEvents\Admin\Ticket_Editor;
use ReEvents\CLI\Test_Factory;
use ReEvents\Data\Ticket_Repository;
use WP_UnitTestCase;

/**
 * Covers the event screen ticket table save path.
 */
final class Ticket_Editor_Test extends WP_UnitTestCase {

	/**
	 * Event used by the tests.
	 *
	 * @var int
	 */
	private int $event = 0;

	/**
	 * Event ID.
	 */
	public function set_up(): void {
		parent::set_up();

		\ReEvents\Capabilities::add();
		delete_option( 'reevents_settings' );

		$admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin );

		$this->event = Test_Factory::event( [ 'title' => 'Ticket editor event' ] );
	}

	/**
	 * Clear request data.
	 */
	public function tear_down(): void {
		$_POST = [];

		parent::tear_down();
	}

	/**
	 * Submit one ticket row.
	 *
	 * @param array<string, string> $row Row values.
	 */
	private function submit( array $row ): void {
		$_POST['reevents_tickets_nonce'] = wp_create_nonce( 'reevents_save_tickets' );
		$_POST['reevents_tickets']       = [ $row ];
	}

	/**
	 * A row creates a ticket with a converted price.
	 */
	public function test_save_creates_ticket(): void {
		$this->submit(
			[
				'id'              => '0',
				'name'            => 'General',
				'price'           => '25.50',
				'currency'        => 'eur',
				'capacity'        => '10',
				'min_per_booking' => '1',
				'max_per_booking' => '4',
				'status'          => 'active',
				'delete'          => '0',
			]
		);

		Ticket_Editor::save( $this->event );

		$tickets = ( new Ticket_Repository() )->for_event( $this->event );

		self::assertCount( 1, $tickets );
		self::assertSame( 'General', $tickets[0]['name'] );
		self::assertSame( 2550, $tickets[0]['price_minor'] );
		self::assertSame( 'EUR', $tickets[0]['currency'] );
		self::assertSame( 10, $tickets[0]['capacity'] );
		self::assertSame( 4, $tickets[0]['max_per_booking'] );
	}

	/**
	 * Comma decimals and thousands separators are normalized.
	 */
	public function test_price_formats(): void {
		$this->submit(
			[
				'id'       => '0',
				'name'     => 'Comma decimal',
				'price'    => '25,50',
				'currency' => 'EUR',
				'delete'   => '0',
			]
		);

		Ticket_Editor::save( $this->event );

		$tickets = ( new Ticket_Repository() )->for_event( $this->event );

		self::assertCount( 1, $tickets );
		self::assertSame( 2550, $tickets[0]['price_minor'] );

		$this->submit(
			[
				'id'       => '0',
				'name'     => 'Thousands',
				'price'    => '1,250.00',
				'currency' => 'USD',
				'delete'   => '0',
			]
		);

		Ticket_Editor::save( $this->event );

		$this->submit(
			[
				'id'       => '0',
				'name'     => 'EU thousands',
				'price'    => '1.234,56',
				'currency' => 'EUR',
				'delete'   => '0',
			]
		);

		Ticket_Editor::save( $this->event );

		$tickets = ( new Ticket_Repository() )->for_event( $this->event );
		$named   = array_column( $tickets, null, 'name' );

		self::assertCount( 3, $tickets );
		self::assertSame( 125000, $named['Thousands']['price_minor'] );
		self::assertSame( 123456, $named['EU thousands']['price_minor'] );
	}

	/**
	 * Invalid prices are refused instead of stored as zero.
	 */
	public function test_invalid_price_is_refused(): void {
		$this->submit(
			[
				'id'       => '0',
				'name'     => 'Bad price',
				'price'    => 'free',
				'currency' => 'USD',
				'delete'   => '0',
			]
		);

		Ticket_Editor::save( $this->event );

		self::assertSame( [], ( new Ticket_Repository() )->for_event( $this->event ) );
	}

	/**
	 * Rows update and delete existing tickets.
	 */
	public function test_save_updates_and_deletes(): void {
		$repo = new Ticket_Repository();
		$id   = (int) $repo->save(
			[
				'event_id'    => $this->event,
				'name'        => 'Old name',
				'price_minor' => 1000,
				'currency'    => 'USD',
			]
		);

		$this->submit(
			[
				'id'       => (string) $id,
				'name'     => 'New name',
				'price'    => '10.00',
				'currency' => 'USD',
				'capacity' => '5',
				'status'   => 'active',
				'delete'   => '0',
			]
		);

		Ticket_Editor::save( $this->event );

		self::assertSame( 'New name', $repo->find( $id )['name'] );

		$this->submit(
			[
				'id'     => (string) $id,
				'name'   => 'New name',
				'delete' => '1',
			]
		);

		Ticket_Editor::save( $this->event );

		self::assertNull( $repo->find( $id ) );
	}

	/**
	 * Rows cannot update or delete tickets from another event.
	 */
	public function test_cannot_touch_another_events_ticket(): void {
		$other_event = Test_Factory::event( [ 'title' => 'Other event' ] );
		$repo        = new Ticket_Repository();
		$ticket      = (int) $repo->save(
			[
				'event_id'    => $other_event,
				'name'        => 'Other ticket',
				'price_minor' => 0,
				'currency'    => 'USD',
			]
		);

		$this->submit(
			[
				'id'       => (string) $ticket,
				'name'     => 'Hijacked',
				'price'    => '5.00',
				'currency' => 'USD',
				'delete'   => '0',
			]
		);

		Ticket_Editor::save( $this->event );

		self::assertSame( 'Other ticket', $repo->find( $ticket )['name'] );

		$this->submit(
			[
				'id'     => (string) $ticket,
				'name'   => 'Hijacked',
				'delete' => '1',
			]
		);

		Ticket_Editor::save( $this->event );

		self::assertNotNull( $repo->find( $ticket ) );
	}

	/**
	 * A missing nonce or capability writes nothing.
	 */
	public function test_save_requires_nonce_and_capability(): void {
		$_POST['reevents_tickets'] = [
			[
				'id'     => '0',
				'name'   => 'Nope',
				'price'  => '1.00',
				'delete' => '0',
			],
		];

		Ticket_Editor::save( $this->event );

		self::assertSame( [], ( new Ticket_Repository() )->for_event( $this->event ) );

		$this->submit(
			[
				'id'     => '0',
				'name'   => 'Nope',
				'price'  => '1.00',
				'delete' => '0',
			]
		);

		$subscriber = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber );

		Ticket_Editor::save( $this->event );

		self::assertSame( [], ( new Ticket_Repository() )->for_event( $this->event ) );
	}
}

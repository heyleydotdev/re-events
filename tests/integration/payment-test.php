<?php
/**
 * Payment integration tests.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Tests\Integration;

use ReEvents\CLI\Test_Factory;
use ReEvents\Data\Booking_Repository;
use ReEvents\Data\Payment_Repository;
use ReEvents\Services\Booking_Service;
use ReEvents\Services\Payment_Service;
use WP_UnitTestCase;

/**
 * Covers the mock gateway and payment state changes.
 */
final class Payment_Test extends WP_UnitTestCase {

	/**
	 * Original settings.
	 *
	 * @var array<string, mixed>
	 */
	private array $settings = [];

	/**
	 * Prevent booking mail from leaving the test process.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->settings = (array) get_option( 'reevents_settings', [] );
		add_filter( 'pre_wp_mail', '__return_true' );
	}

	/**
	 * Restore settings.
	 */
	public function tear_down(): void {
		update_option( 'reevents_settings', $this->settings );

		parent::tear_down();
	}

	/**
	 * A verified webhook confirms the booking.
	 */
	public function test_webhook_confirms_booking(): void {
		$event  = Test_Factory::event( [ 'title' => 'Payment test' ] );
		$ticket = Test_Factory::ticket(
			$event,
			[
				'capacity'    => 1,
				'price_minor' => 1000,
			]
		);
		$id     = Booking_Service::create(
			[
				'event_id'  => $event,
				'ticket_id' => $ticket,
				'quantity'  => 1,
			]
		);

		self::assertIsInt( $id );

		$start = Payment_Service::start( $id, 'mock' );

		self::assertIsArray( $start );
		self::assertSame( 'pending', $start['status'] );

		$result = Payment_Service::handle_webhook(
			'mock',
			(string) wp_json_encode(
				[
					'gateway_ref' => $start['gateway_ref'],
					'status'      => 'succeeded',
				]
			),
			[]
		);

		self::assertTrue( $result );

		$booking = ( new Booking_Repository() )->find( $id );
		self::assertSame( 'confirmed', $booking['status'] );

		$payment = ( new Payment_Repository() )->find( $start['payment_id'] );
		self::assertSame( 'succeeded', $payment['status'] );
	}

	/**
	 * A replayed success webhook does not resurrect a cancelled booking.
	 */
	public function test_replayed_success_does_not_resurrect_cancelled_booking(): void {
		$event  = Test_Factory::event( [ 'title' => 'Replay test' ] );
		$ticket = Test_Factory::ticket(
			$event,
			[
				'capacity'    => 1,
				'price_minor' => 1000,
			]
		);
		$id     = Booking_Service::create(
			[
				'event_id'  => $event,
				'ticket_id' => $ticket,
				'quantity'  => 1,
			]
		);

		self::assertIsInt( $id );

		$start = Payment_Service::start( $id, 'mock' );

		self::assertIsArray( $start );

		$payload = (string) wp_json_encode(
			[
				'gateway_ref' => $start['gateway_ref'],
				'status'      => 'succeeded',
			]
		);

		Payment_Service::handle_webhook( 'mock', $payload, [] );
		Payment_Service::refund( $start['payment_id'] );
		Payment_Service::handle_webhook( 'mock', $payload, [] );

		$booking = ( new Booking_Repository() )->find( $id );

		self::assertSame( 'cancelled', $booking['status'] );
	}

	/**
	 * A paid booking stays pending when approval is required.
	 */
	public function test_payment_keeps_pending_when_approval_required(): void {
		$settings                     = get_option( 'reevents_settings', [] );
		$settings                     = is_array( $settings ) ? $settings : [];
		$settings['require_approval'] = true;
		update_option( 'reevents_settings', $settings );

		$event  = Test_Factory::event( [ 'title' => 'Approval test' ] );
		$ticket = Test_Factory::ticket(
			$event,
			[
				'capacity'    => 1,
				'price_minor' => 1000,
			]
		);
		$id     = Booking_Service::create(
			[
				'event_id'  => $event,
				'ticket_id' => $ticket,
				'quantity'  => 1,
			]
		);

		self::assertIsInt( $id );

		$start = Payment_Service::start( $id, 'mock' );

		self::assertIsArray( $start );

		Payment_Service::handle_webhook(
			'mock',
			(string) wp_json_encode(
				[
					'gateway_ref' => $start['gateway_ref'],
					'status'      => 'succeeded',
				]
			),
			[]
		);

		$booking = ( new Booking_Repository() )->find( $id );

		self::assertSame( 'pending', $booking['status'] );

		$settings['require_approval'] = false;
		update_option( 'reevents_settings', $settings );
	}

	/**
	 * A full refund cancels the booking.
	 */
	public function test_refund_cancels_booking(): void {
		$event  = Test_Factory::event( [ 'title' => 'Refund test' ] );
		$ticket = Test_Factory::ticket(
			$event,
			[
				'capacity'    => 1,
				'price_minor' => 1000,
			]
		);
		$id     = Booking_Service::create(
			[
				'event_id'  => $event,
				'ticket_id' => $ticket,
				'quantity'  => 1,
			]
		);

		self::assertIsInt( $id );

		$start = Payment_Service::start( $id, 'mock' );

		self::assertIsArray( $start );
		self::assertTrue( Payment_Service::refund( $start['payment_id'] ) );

		$booking = ( new Booking_Repository() )->find( $id );
		self::assertSame( 'cancelled', $booking['status'] );
	}
}

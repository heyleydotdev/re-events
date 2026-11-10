<?php
/**
 * Payment checkout integration tests.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Tests\Integration;

use ReEvents\CLI\Test_Factory;
use ReEvents\Data\Payment_Repository;
use ReEvents\Services\Booking_Service;
use ReEvents\Services\Payment_Service;
use WP_UnitTestCase;

/**
 * Covers checkout start and reuse.
 */
final class Payment_Checkout_Test extends WP_UnitTestCase {

	/**
	 * Original settings.
	 *
	 * @var array<string, mixed>
	 */
	private array $settings = [];

	/**
	 * Save the settings.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->settings = (array) get_option( 'reevents_settings', [] );
	}

	/**
	 * Restore the settings.
	 */
	public function tear_down(): void {
		update_option( 'reevents_settings', $this->settings );

		parent::tear_down();
	}

	/**
	 * Set the gateway.
	 *
	 * @param string $gateway Gateway ID.
	 */
	private function gateway( string $gateway ): void {
		$settings                    = (array) get_option( 'reevents_settings', [] );
		$settings['payment_gateway'] = $gateway;
		update_option( 'reevents_settings', $settings );
	}

	/**
	 * A priced booking for the given ticket.
	 *
	 * @param int $price_minor Price in the smallest currency unit.
	 */
	private function booking( int $price_minor ): int {
		$event  = Test_Factory::event( [ 'title' => 'Checkout event' ] );
		$ticket = Test_Factory::ticket(
			$event,
			[
				'price_minor' => $price_minor,
				'capacity'    => 10,
			]
		);

		return Test_Factory::booking( $event, $ticket );
	}

	/**
	 * No gateway means no checkout.
	 */
	public function test_no_gateway_skips_checkout(): void {
		$this->gateway( 'none' );

		self::assertSame( '', Payment_Service::checkout( $this->booking( 500 ) ) );
	}

	/**
	 * A free booking never needs checkout.
	 */
	public function test_free_booking_skips_checkout(): void {
		$this->gateway( 'mock' );

		self::assertSame( '', Payment_Service::checkout( $this->booking( 0 ) ) );
	}

	/**
	 * A pending payment with a redirect URL is reused.
	 */
	public function test_reuses_pending_redirect(): void {
		$this->gateway( 'mock' );

		$booking_id = $this->booking( 500 );
		$repo       = new Payment_Repository();

		self::assertSame( '', Payment_Service::checkout( $booking_id ) );

		$payments = $repo->for_booking( $booking_id );
		self::assertCount( 1, $payments );

		$repo->update(
			(int) $payments[0]['id'],
			[
				'data' => [
					'redirect_url' => 'https://example.com/pay',
					'session_id'   => 'cs_test',
				],
			]
		);

		self::assertSame( 'https://example.com/pay', Payment_Service::checkout( $booking_id ) );
		self::assertCount( 1, $repo->for_booking( $booking_id ) );
	}

	/**
	 * A confirmed booking never starts a new payment.
	 */
	public function test_confirmed_booking_skips_checkout(): void {
		$this->gateway( 'mock' );

		$booking_id = $this->booking( 500 );
		Booking_Service::update_status( $booking_id, 'confirmed' );

		self::assertSame( '', Payment_Service::checkout( $booking_id ) );
	}
}

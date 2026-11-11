<?php
/**
 * Cached booking token tests.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Tests\Integration;

use ReEvents\CLI\Test_Factory;
use ReEvents\Services\Booking_Service;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Covers idempotent booking tokens and email ownership.
 */
final class Booking_Token_Test extends WP_UnitTestCase {

	/**
	 * Event ID.
	 *
	 * @var int
	 */
	private int $event = 0;

	/**
	 * Ticket ID.
	 *
	 * @var int
	 */
	private int $ticket = 0;

	/**
	 * Victim booking ID.
	 *
	 * @var int
	 */
	private int $booking = 0;

	/**
	 * Create an event, ticket and one booking.
	 */
	public function set_up(): void {
		parent::set_up();

		\ReEvents\Capabilities::add();
		delete_option( 'reevents_settings' );

		$this->event   = Test_Factory::event(
			[
				'title' => 'Token event',
				'meta'  => [
					'_reevents_start_utc' => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) ),
					'_reevents_end_utc'   => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day +1 hour' ) ),
					'_reevents_timezone'  => 'UTC',
				],
			]
		);
		$this->ticket  = Test_Factory::ticket( $this->event );
		$this->booking = Test_Factory::booking(
			$this->event,
			$this->ticket,
			[
				'attendees' => [
					[
						'name'  => 'Victim',
						'email' => 'victim@example.com',
					],
				],
			]
		);

		set_transient( Booking_Service::token_cache_key( 'shared-token', $this->event ), $this->booking, 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Build a create request.
	 *
	 * @param string $email Email.
	 */
	private function request( string $email ): WP_REST_Request {
		$request = new WP_REST_Request( 'POST', '/re-events/v1/events/' . $this->event . '/bookings' );
		$request->set_body_params(
			[
				'event_id'      => $this->event,
				'ticket_id'     => $this->ticket,
				'quantity'      => 1,
				'booking_token' => 'shared-token',
				'attendees'     => [
					[
						'name'  => 'Visitor',
						'email' => $email,
					],
				],
			]
		);

		return $request;
	}

	/**
	 * A cached token does not reveal another visitor's booking.
	 */
	public function test_cached_token_requires_matching_email(): void {
		$response = rest_do_request( $this->request( 'attacker@example.com' ) );
		$data     = $response->get_data();

		self::assertNotSame( $this->booking, (int) ( $data['id'] ?? 0 ) );
		self::assertStringNotContainsString( 'victim@example.com', (string) wp_json_encode( $data ) );
	}

	/**
	 * The original visitor still gets the cached booking back.
	 */
	public function test_cached_token_returns_own_booking(): void {
		$response = rest_do_request( $this->request( 'victim@example.com' ) );
		$data     = $response->get_data();

		self::assertSame( $this->booking, (int) ( $data['id'] ?? 0 ) );
	}
}

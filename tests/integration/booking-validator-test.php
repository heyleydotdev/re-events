<?php
/**
 * Booking validator integration tests.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Tests\Integration;

use ReEvents\CLI\Test_Factory;
use ReEvents\Services\Booking_Validator;
use WP_UnitTestCase;

/**
 * Covers the field-level booking validation map.
 */
final class Booking_Validator_Test extends WP_UnitTestCase {

	/**
	 * Use a clean attendee field set.
	 */
	public function set_up(): void {
		parent::set_up();

		$settings                    = get_option( 'reevents_settings', [] );
		$settings                    = is_array( $settings ) ? $settings : [];
		$settings['attendee_fields'] = [];
		update_option( 'reevents_settings', $settings );
	}

	/**
	 * Invalid email and missing name return field errors.
	 */
	public function test_invalid_attendee_fields(): void {
		$errors = Booking_Validator::validate(
			[
				'event_id'  => 1,
				'ticket_id' => 1,
				'quantity'  => 1,
				'attendees' => [
					[
						'name'  => '',
						'email' => 'not-an-email',
					],
				],
			]
		);

		self::assertArrayHasKey( 'attendees.0.name', $errors );
		self::assertArrayHasKey( 'attendees.0.email', $errors );
	}

	/**
	 * Cancelled events return an event field error.
	 */
	public function test_cancelled_event_domain_error(): void {
		$event  = Test_Factory::event( [ 'title' => 'Validator event' ] );
		$ticket = Test_Factory::ticket( $event );

		update_post_meta( $event, '_reevents_status', 'cancelled' );

		$result = Booking_Validator::validate_domain( $event, $ticket, 1, 0, [] );

		self::assertArrayHasKey( 'event_id', $result['errors'] );
		self::assertSame( 'reevents_booking_event_cancelled', $result['code'] );
	}

	/**
	 * A valid payload returns no errors.
	 */
	public function test_valid_payload(): void {
		$errors = Booking_Validator::validate(
			[
				'event_id'      => 1,
				'ticket_id'     => 1,
				'quantity'      => 1,
				'booking_token' => 'token',
				'attendees'     => [
					[
						'name'  => 'Jane',
						'email' => 'jane@example.com',
					],
				],
			]
		);

		self::assertSame( [], $errors );
	}
}

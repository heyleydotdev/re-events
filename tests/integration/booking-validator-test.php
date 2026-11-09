<?php
/**
 * Booking validator integration tests.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Tests\Integration;

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

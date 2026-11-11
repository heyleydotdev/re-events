<?php
/**
 * Settings tests.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Tests\Integration;

use ReEvents\Admin\Settings;
use ReEvents\CLI\Test_Factory;
use WP_UnitTestCase;

/**
 * Covers settings that affect other services.
 */
final class Settings_Test extends WP_UnitTestCase {

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

		\ReEvents\Capabilities::add();
		delete_option( 'reevents_settings' );

		$this->admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $this->admin );
	}

	/**
	 * Checkbox attendee fields are accepted and required.
	 */
	public function test_checkbox_attendee_field(): void {
		$settings = Settings::sanitize(
			[
				'reevents_tab'    => 'bookings',
				'attendee_fields' => [
					[
						'key'      => 'consent',
						'label'    => 'I agree',
						'type'     => 'checkbox',
						'required' => 1,
					],
				],
			]
		);

		self::assertSame( 'checkbox', $settings['attendee_fields']['consent']['type'] );

		update_option( 'reevents_settings', $settings );

		$errors = \ReEvents\Services\Booking_Validator::validate(
			[
				'event_id'  => 1,
				'ticket_id' => 1,
				'quantity'  => 1,
				'attendees' => [
					[
						'name'  => 'Jane',
						'email' => 'jane@example.com',
					],
				],
			]
		);

		self::assertArrayHasKey( 'attendees.0.fields.consent', $errors );
	}
}

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
	 * The default currency is sanitized.
	 */
	public function test_default_currency_is_sanitized(): void {
		$settings = Settings::sanitize(
			[
				'reevents_tab'     => 'payments',
				'default_currency' => 'eur',
			]
		);

		self::assertSame( 'EUR', $settings['default_currency'] );

		$invalid = Settings::sanitize(
			[
				'reevents_tab'     => 'payments',
				'default_currency' => 'x',
			]
		);

		self::assertSame( 'USD', $invalid['default_currency'] );
	}

	/**
	 * Blank secret fields keep the stored value.
	 */
	public function test_secrets_are_kept_when_blank(): void {
		update_option(
			'reevents_settings',
			[
				'stripe_secret_key'    => 'sk_old',
				'paypal_client_secret' => 'pp_old',
			]
		);

		$settings = Settings::sanitize( [ 'reevents_tab' => 'payments' ] );

		self::assertSame( 'sk_old', $settings['stripe_secret_key'] );
		self::assertSame( 'pp_old', $settings['paypal_client_secret'] );

		$settings = Settings::sanitize(
			[
				'reevents_tab'      => 'payments',
				'stripe_secret_key' => 'sk_new',
			]
		);

		self::assertSame( 'sk_new', $settings['stripe_secret_key'] );
		self::assertSame( 'pp_old', $settings['paypal_client_secret'] );
	}

	/**
	 * Email sender and recipient fields are sanitized.
	 */
	public function test_email_sender_settings_are_sanitized(): void {
		$settings = Settings::sanitize(
			[
				'reevents_tab'           => 'emails',
				'email_admin_recipients' => 'one@example.com, bad, two@example.com',
				'email_from_name'        => 'ReEvents',
				'email_from_address'     => 'events@example.com',
				'email_reply_to'         => 'reply@example.com',
			]
		);

		self::assertSame( 'one@example.com, two@example.com', $settings['email_admin_recipients'] );
		self::assertSame( 'events@example.com', $settings['email_from_address'] );
		self::assertSame( 'reply@example.com', $settings['email_reply_to'] );

		$invalid = Settings::sanitize(
			[
				'reevents_tab'       => 'emails',
				'email_from_address' => 'not an email',
				'email_reply_to'     => 'not an email',
			]
		);

		self::assertSame( '', $invalid['email_from_address'] );
		self::assertSame( '', $invalid['email_reply_to'] );
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

	/**
	 * New tickets use the setting when no currency is passed.
	 */
	public function test_ticket_uses_default_currency(): void {
		update_option( 'reevents_settings', [ 'default_currency' => 'EUR' ] );

		$event = Test_Factory::event(
			[
				'title' => 'Currency event',
				'meta'  => [
					'_reevents_start_utc' => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) ),
					'_reevents_end_utc'   => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day +1 hour' ) ),
					'_reevents_timezone'  => 'UTC',
				],
			]
		);

		$ticket = \reevents_create_ticket( $event, [ 'name' => 'Currency ticket' ] );

		self::assertIsArray( $ticket );
		self::assertSame( 'EUR', $ticket['currency'] );
	}
}

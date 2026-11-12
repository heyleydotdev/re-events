<?php
/**
 * Booking email integration tests.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Tests\Integration;

use ReEvents\Admin\Settings;
use ReEvents\CLI\Test_Factory;
use ReEvents\Data\Booking_Repository;
use ReEvents\Services\Booking_Emails;
use ReEvents\Services\Booking_Service;
use ReEvents\Services\Email_Design;
use ReEvents\Services\Payment_Service;
use WP_UnitTestCase;

/**
 * Covers mail content, lifecycle, headers and format detection.
 */
final class Email_Test extends WP_UnitTestCase {

	/**
	 * Original settings.
	 *
	 * @var array<string, mixed>
	 */
	private array $settings = [];

	/**
	 * Captured mails.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $mails = [];

	/**
	 * Prepare settings and mail capture.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->settings = (array) get_option( 'reevents_settings', [] );
		$this->mails    = [];

		add_filter( 'option_active_plugins', [ self::class, 'active_plugins' ] );
		add_filter(
			'pre_wp_mail',
			function ( $short_circuit, $atts ) {
				unset( $short_circuit );

				$this->mails[] = $atts;

				return true;
			},
			10,
			2
		);
	}

	/**
	 * Restore state.
	 */
	public function tear_down(): void {
		update_option( 'reevents_settings', $this->settings );
		remove_all_filters( 'option_active_plugins' );
		remove_all_filters( 'pre_wp_mail' );
		remove_all_filters( 'reevents_email_format' );

		parent::tear_down();
	}

	/**
	 * Keep the plugin list predictable.
	 *
	 * @return string[]
	 */
	public static function active_plugins(): array {
		return [ 're-events/re-events.php' ];
	}

	/**
	 * Use default settings with overrides.
	 *
	 * @param array<string, mixed> $overrides Settings.
	 */
	private function settings( array $overrides = [] ): void {
		update_option( 'reevents_settings', array_merge( Settings::defaults(), $overrides ) );
	}

	/**
	 * Create an event with a venue and ticket.
	 *
	 * @return array{event:int,ticket:int,venue:int}
	 */
	private function fixture(): array {
		$venue = wp_insert_post(
			[
				'post_type'   => 'reevents_venue',
				'post_status' => 'publish',
				'post_title'  => 'Email venue',
				'meta_input'  => [ '_reevents_address' => '1 Main Street' ],
			]
		);

		$event = Test_Factory::event(
			[
				'title' => 'Email event',
				'meta'  => [
					'_reevents_start_utc' => gmdate( 'Y-m-d H:i:s', strtotime( '+7 days' ) ),
					'_reevents_end_utc'   => gmdate( 'Y-m-d H:i:s', strtotime( '+7 days +1 hour' ) ),
					'_reevents_timezone'  => 'UTC',
					'_reevents_status'    => 'scheduled',
					'_reevents_venue_id'  => $venue,
				],
			]
		);

		$ticket = Test_Factory::ticket(
			$event,
			[
				'name'        => 'General',
				'price_minor' => 2500,
			]
		);

		return [
			'event'  => $event,
			'ticket' => $ticket,
			'venue'  => $venue,
		];
	}


	/**
	 * Create a booking with one attendee.
	 *
	 * @param array{event:int,ticket:int,venue:int} $fixture Fixture.
	 */
	private function booking( array $fixture ): int {
		return Test_Factory::booking(
			$fixture['event'],
			$fixture['ticket'],
			[
				'attendees' => [
					[
						'name'  => 'Ada',
						'email' => 'ada@example.com',
					],
				],
			]
		);
	}

	/**
	 * Compose resolves tokens and appends the details block.
	 */
	public function test_compose_contains_placeholders_and_details(): void {
		$fixture = $this->fixture();
		$event   = ( new \ReEvents\Data\Event_Repository() )->find( $fixture['event'] );
		$booking = [
			'event_id'    => $fixture['event'],
			'ticket_id'   => $fixture['ticket'],
			'reference'   => 'REF-123',
			'quantity'    => 2,
			'total_minor' => 5000,
			'currency'    => 'USD',
			'status'      => 'confirmed',
		];

		$mail = Booking_Emails::compose(
			$booking,
			$event,
			[
				[
					'name'  => 'Ada',
					'email' => 'ada@example.com',
				],
			],
			'attendee'
		);

		self::assertStringContainsString( 'Email event', $mail['subject'] );
		self::assertStringContainsString( 'REF-123', $mail['body'] );
		self::assertStringContainsString( 'General', $mail['body'] );
		self::assertStringContainsString( '1 Main Street', $mail['body'] );
		self::assertStringContainsString( 'Status: Confirmed', $mail['body'] );
		self::assertContains( '{event_url}', Booking_Emails::tokens() );
		self::assertContains( '{status_label}', Booking_Emails::tokens() );
	}

	/**
	 * Admin recipients and sender headers are applied.
	 */
	public function test_admin_recipients_and_headers(): void {
		$this->settings(
			[
				'email_admin_recipients' => 'one@example.com, two@example.com',
				'email_from_name'        => 'ReEvents',
				'email_from_address'     => 'events@example.com',
				'email_reply_to'         => 'reply@example.com',
			]
		);

		$fixture = $this->fixture();
		$id      = $this->booking( $fixture );

		$this->mails = [];
		Booking_Emails::send( $id, true );

		self::assertCount( 1, $this->mails );
		self::assertSame( [ 'one@example.com', 'two@example.com' ], $this->mails[0]['to'] );
		self::assertContains( 'From: ReEvents <events@example.com>', $this->mails[0]['headers'] );
		self::assertContains( 'Reply-To: reply@example.com', $this->mails[0]['headers'] );
		self::assertContains( 'Content-Type: text/html; charset=UTF-8', $this->mails[0]['headers'] );
	}

	/**
	 * A cancellation notifies the admin and queues the attendee copy.
	 */
	public function test_cancelled_booking_sends_both_copies(): void {
		$this->settings();

		$fixture = $this->fixture();
		$id      = $this->booking( $fixture );

		$this->mails = [];
		Booking_Service::update_status( $id, 'cancelled' );

		self::assertSame( [], $this->mails );
		self::assertNotFalse( wp_next_scheduled( 'reevents_send_booking_admin_email', [ $id ] ) );
		self::assertNotFalse( wp_next_scheduled( 'reevents_send_booking_emails', [ $id ] ) );

		do_action( 'reevents_send_booking_admin_email', $id );
		do_action( 'reevents_send_booking_emails', $id );

		$booking = ( new Booking_Repository() )->find( $id );
		self::assertSame( 'cancelled', $booking['status'] );
		self::assertCount( 2, $this->mails );
		self::assertStringContainsString( 'Cancelled', (string) $this->mails[0]['subject'] );
		self::assertStringContainsString( 'Cancelled', (string) $this->mails[1]['subject'] );

		wp_clear_scheduled_hook( 'reevents_send_booking_admin_email', [ $id ] );
		wp_clear_scheduled_hook( 'reevents_send_booking_emails', [ $id ] );
	}

	/**
	 * A pending status change does not send mail.
	 */
	public function test_pending_status_change_sends_nothing(): void {
		$this->settings();

		$fixture = $this->fixture();
		$id      = $this->booking( $fixture );

		$this->mails = [];
		do_action( 'reevents_booking_status_changed', $id, 'pending' );

		self::assertSame( [], $this->mails );
	}

	/**
	 * Paid approvals get one await-approval mail.
	 */
	public function test_approval_mail_after_payment(): void {
		$this->settings( [ 'require_approval' => true ] );

		$fixture = $this->fixture();
		$id      = $this->booking( $fixture );
		$start   = Payment_Service::start( $id, 'mock' );

		self::assertIsArray( $start );

		$this->mails = [];
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

		self::assertNotEmpty( $this->mails );
		self::assertStringContainsString( 'Awaiting approval', (string) $this->mails[0]['subject'] );
		self::assertStringContainsString( 'Awaiting approval', (string) $this->mails[0]['message'] );
	}

	/**
	 * A template that already lists the core fields skips the details block.
	 */
	public function test_self_contained_template_skips_details(): void {
		$this->settings(
			[
				'email_admin_body' => 'Event: {event}
Reference: {reference}
Total: {total}',
			]
		);

		$fixture = $this->fixture();
		$event   = ( new \ReEvents\Data\Event_Repository() )->find( $fixture['event'] );
		$booking = [
			'event_id'    => $fixture['event'],
			'ticket_id'   => $fixture['ticket'],
			'reference'   => 'REF-123',
			'quantity'    => 2,
			'total_minor' => 5000,
			'currency'    => 'USD',
			'status'      => 'confirmed',
		];

		$mail = Booking_Emails::compose( $booking, $event, [], 'admin' );

		self::assertSame( [], $mail['details'] );
		self::assertSame( 1, substr_count( $mail['body'], 'Reference:' ) );
	}

	/**
	 * The HTML alternative is handed to PHPMailer.
	 */
	public function test_html_alt_body_is_applied(): void {
		$property = new \ReflectionProperty( Booking_Emails::class, 'alt_body' );
		$property->setAccessible( true );
		$property->setValue( null, 'Plain fallback' );

		$mailer = new \stdClass();
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
		$mailer->AltBody = '';
		Booking_Emails::phpmailer_init( $mailer );

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
		self::assertSame( 'Plain fallback', $mailer->AltBody );
		$property->setValue( null, '' );
	}

	/**
	 * Format choice and preview follow the detected designer.
	 */
	public function test_format_detection_and_preview(): void {
		$this->settings();

		self::assertSame( 'html', Email_Design::format() );

		$preview = Booking_Emails::preview( 'attendee' );

		self::assertSame( 'html', $preview['format'] );
		self::assertStringContainsString( 'Sample Event', $preview['html'] );
		self::assertStringContainsString( '<table', $preview['html'] );

		remove_all_filters( 'option_active_plugins' );
		add_filter( 'option_active_plugins', static fn(): array => [ 'wp-html-mail/wp-html-mail.php' ] );

		$designer = Email_Design::detected();

		self::assertSame( 'Email Template Designer (WP HTML Mail)', $designer );
		self::assertSame( 'plain', Email_Design::format() );

		$plain = Booking_Emails::preview( 'attendee' );

		self::assertSame( '', $plain['html'] );
		self::assertStringContainsString( 'Sample Event', $plain['text'] );
	}

	/**
	 * A per-event override is used by the preview.
	 */
	public function test_preview_respects_event_override(): void {
		$this->settings();

		$fixture = $this->fixture();
		update_post_meta( $fixture['event'], '_reevents_email_attendee_subject', 'Custom {event}' );

		$preview = Booking_Emails::preview( 'attendee', $fixture['event'] );

		self::assertSame( 'Custom Email event', $preview['subject'] );
	}
}

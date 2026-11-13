<?php
/**
 * Booking emails.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Services;

use ReEvents\Admin\Settings;
use ReEvents\Data\Booking_Repository;
use ReEvents\Data\Event_Repository;
use ReEvents\Data\Ticket_Repository;
use ReEvents\Domain\Event;
use ReEvents\Render\Event_Format;
use ReEvents\Render\Template;
use ReEvents\Services\Booking_Access;

/**
 * Sends booking notifications.
 */
final class Booking_Emails {

	/**
	 * Plain-text alternative for the next HTML message.
	 *
	 * @var string
	 */
	private static string $alt_body = '';

	/**
	 * Hook into WordPress.
	 */
	public static function register(): void {
		add_action( 'reevents_booking_created', [ self::class, 'send' ], 10, 1 );
		add_action( 'reevents_booking_status_changed', [ self::class, 'on_status_change' ], 10, 2 );
		add_action( 'reevents_send_booking_emails', [ self::class, 'send_attendees' ], 10, 1 );
		add_action( 'reevents_send_booking_admin_email', [ self::class, 'send_admin_notice' ], 10, 1 );
		add_action( 'phpmailer_init', [ self::class, 'phpmailer_init' ] );
	}

	/**
	 * Placeholder tokens available to every template.
	 *
	 * @return string[]
	 */
	public static function tokens(): array {
		return [
			'{event}',
			'{event_url}',
			'{date}',
			'{event_end}',
			'{timezone}',
			'{venue}',
			'{venue_address}',
			'{ticket}',
			'{reference}',
			'{manage_url}',
			'{name}',
			'{email}',
			'{quantity}',
			'{total}',
			'{calendar_url}',
			'{site}',
			'{site_url}',
			'{status}',
			'{status_label}',
		];
	}

	/**
	 * Send emails for a new booking.
	 *
	 * @param int  $booking_id   Booking ID.
	 * @param bool $notify_admin Send the admin copy.
	 */
	public static function send( int $booking_id, bool $notify_admin = true ): void {
		$booking = ( new Booking_Repository() )->find( $booking_id );

		if ( ! $booking ) {
			return;
		}

		if ( $notify_admin ) {
			self::send_admin( $booking_id );
		}

		if ( 'confirmed' === (string) $booking['status'] ) {
			self::schedule_attendees( $booking_id );
		}
	}

	/**
	 * Send the attendee copies outside the request.
	 *
	 * @param int    $booking_id   Booking ID.
	 * @param string $status_label Optional status label for approval mail.
	 */
	public static function send_attendees( int $booking_id, string $status_label = '' ): void {
		$repo    = new Booking_Repository();
		$booking = $repo->find( $booking_id );

		if ( ! $booking ) {
			return;
		}

		$event       = ( new Event_Repository() )->find( (int) $booking['event_id'] );
		$attendees   = $repo->attendees( $booking_id );
		$ticket_name = self::ticket_name( $booking );

		foreach ( $attendees as $attendee ) {
			if ( ! is_email( (string) $attendee['email'] ) ) {
				continue;
			}

			self::send_mail( (string) $attendee['email'], self::build_mail( $booking, $event, [ $attendee ], 'attendee', $status_label, $ticket_name ) );
		}
	}

	/**
	 * Send the admin copy from the queue.
	 *
	 * @param int $booking_id Booking ID.
	 */
	public static function send_admin_notice( int $booking_id ): void {
		self::send_admin( $booking_id );
	}

	/**
	 * React to a booking status change.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $status     New status.
	 */
	public static function on_status_change( int $booking_id, string $status ): void {
		if ( 'confirmed' === $status ) {
			self::send( $booking_id, false );
			return;
		}

		if ( in_array( $status, [ 'cancelled', 'rejected' ], true ) ) {
			self::schedule_attendees( $booking_id );

			if ( 'cancelled' === $status ) {
				self::schedule_admin( $booking_id );
			}
		}
	}

	/**
	 * Build an email.
	 *
	 * @param array<string, mixed> $booking      Booking.
	 * @param Event|null           $event        Event.
	 * @param array<int, mixed>    $attendees    Attendees.
	 * @param string               $type         admin or attendee.
	 * @param string               $status_label Optional status label.
	 * @param string|null          $ticket_name  Ticket name, when already loaded.
	 * @return array{subject:string,body:string,message:string,details:array<int, array{label:string,value:string}>}
	 */
	public static function compose( array $booking, ?Event $event, array $attendees, string $type, string $status_label = '', ?string $ticket_name = null ): array {
		$attendee     = isset( $attendees[0] ) && is_array( $attendees[0] ) ? $attendees[0] : [];
		$status       = (string) ( $booking['status'] ?? 'pending' );
		$status_label = '' !== $status_label ? $status_label : self::status_label( $status );
		$ticket_name  = $ticket_name ?? self::ticket_name( $booking );
		$placeholders = self::placeholders( $booking, $event, $attendee, $status_label, $ticket_name );
		$event_id     = $event ? $event->id : (int) ( $booking['event_id'] ?? 0 );

		if ( 'admin' === $type ) {
			$subject  = self::template( 'email_admin_subject', $event_id );
			$template = self::template( 'email_admin_body', $event_id );
		} else {
			$subject  = self::template( 'email_attendee_subject', $event_id );
			$template = self::template( 'email_attendee_body', $event_id );
		}

		// Templates saved before the details block listed the core fields themselves.
		$self_contained = str_contains( $template, '{event}' ) && str_contains( $template, '{reference}' ) && str_contains( $template, '{total}' );
		$message        = strtr( $template, $placeholders );
		$details        = $self_contained ? [] : self::details( $booking, $event, $attendees, $status_label, $type, $ticket_name );
		$body           = $message;

		if ( $details ) {
			$body .= "\n\n" . self::details_text( $details );
		}

		return [
			'subject' => strtr( $subject, $placeholders ),
			'body'    => $body,
			'message' => $message,
			'details' => $details,
		];
	}

	/**
	 * Send a template with sample values.
	 *
	 * @param string $type     admin or attendee.
	 * @param string $email    Recipient.
	 * @param int    $event_id Event for per-event overrides.
	 */
	public static function send_test( string $type, string $email, int $event_id = 0 ): bool {
		[ $booking, $event, $attendees ] = self::sample_context( $event_id );

		return self::send_mail( $email, self::build_mail( $booking, $event, $attendees, 'attendee' === $type ? 'attendee' : 'admin' ) );
	}

	/**
	 * Render a template with sample values without sending it.
	 *
	 * @param string $type     admin or attendee.
	 * @param int    $event_id Event for per-event overrides.
	 * @return array{subject:string,text:string,html:string,format:string}
	 */
	public static function preview( string $type, int $event_id = 0 ): array {
		[ $booking, $event, $attendees ] = self::sample_context( $event_id );
		$mail                            = self::build_mail( $booking, $event, $attendees, 'attendee' === $type ? 'attendee' : 'admin' );

		return [
			'subject' => $mail['subject'],
			'text'    => $mail['text'],
			'html'    => $mail['html'],
			'format'  => Email_Design::format(),
		];
	}

	/**
	 * Set the plain-text alternative on the next PHPMailer message.
	 *
	 * @param mixed $phpmailer PHPMailer instance.
	 */
	public static function phpmailer_init( $phpmailer ): void {
		if ( '' === self::$alt_body || ! is_object( $phpmailer ) || ! property_exists( $phpmailer, 'AltBody' ) ) {
			return;
		}

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
		$phpmailer->AltBody = self::$alt_body;
	}

	/**
	 * Build the mail payload for a format.
	 *
	 * @param array<string, mixed> $booking      Booking.
	 * @param Event|null           $event        Event.
	 * @param array<int, mixed>    $attendees    Attendees.
	 * @param string               $type         admin or attendee.
	 * @param string               $status_label Optional status label.
	 * @param string|null          $ticket_name  Ticket name, when already loaded.
	 * @return array{subject:string,text:string,html:string}
	 */
	private static function build_mail( array $booking, ?Event $event, array $attendees, string $type, string $status_label = '', ?string $ticket_name = null ): array {
		$mail = self::compose( $booking, $event, $attendees, $type, $status_label, $ticket_name );
		$html = '';

		if ( 'html' === Email_Design::format() ) {
			$event_id = $event ? $event->id : (int) ( $booking['event_id'] ?? 0 );
			$html     = Template::render(
				'emails/layout',
				[
					'subject'   => $mail['subject'],
					'message'   => wpautop( esc_html( $mail['message'] ) ),
					'details'   => $mail['details'],
					'event_url' => $event_id > 0 ? (string) get_permalink( $event_id ) : '',
					'site_name' => wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
					'site_url'  => home_url( '/' ),
					'logo_url'  => (string) get_site_icon_url( 96 ),
				]
			);
		}

		return [
			'subject' => $mail['subject'],
			'text'    => $mail['body'],
			'html'    => $html,
		];
	}

	/**
	 * Send one mail with the configured format and headers.
	 *
	 * @param string|string[]                               $to   Recipient or recipients.
	 * @param array{subject:string,text:string,html:string} $mail Mail payload.
	 */
	private static function send_mail( $to, array $mail ): bool {
		$headers = self::headers();

		if ( '' === (string) $mail['html'] ) {
			return (bool) wp_mail( $to, (string) $mail['subject'], (string) $mail['text'], $headers );
		}

		self::$alt_body = (string) $mail['text'];
		$headers[]      = 'Content-Type: text/html; charset=UTF-8';

		try {
			return (bool) wp_mail( $to, (string) $mail['subject'], (string) $mail['html'], $headers );
		} finally {
			self::$alt_body = '';
		}
	}

	/**
	 * Send the admin copy for a booking.
	 *
	 * @param int $booking_id Booking ID.
	 */
	private static function send_admin( int $booking_id ): bool {
		$repo    = new Booking_Repository();
		$booking = $repo->find( $booking_id );

		if ( ! $booking ) {
			return false;
		}

		$event     = ( new Event_Repository() )->find( (int) $booking['event_id'] );
		$attendees = $repo->attendees( $booking_id );

		return self::send_mail( self::admin_recipients(), self::build_mail( $booking, $event, $attendees, 'admin', '', self::ticket_name( $booking ) ) );
	}

	/**
	 * Schedule the attendee copies for a booking.
	 *
	 * @param int $booking_id Booking ID.
	 */
	private static function schedule_attendees( int $booking_id ): void {
		if ( ! wp_next_scheduled( 'reevents_send_booking_emails', [ $booking_id ] ) ) {
			wp_schedule_single_event( time() + 5, 'reevents_send_booking_emails', [ $booking_id ] );
		}
	}

	/**
	 * Schedule the admin cancellation copy.
	 *
	 * @param int $booking_id Booking ID.
	 */
	private static function schedule_admin( int $booking_id ): void {
		if ( ! wp_next_scheduled( 'reevents_send_booking_admin_email', [ $booking_id ] ) ) {
			wp_schedule_single_event( time() + 5, 'reevents_send_booking_admin_email', [ $booking_id ] );
		}
	}

	/**
	 * Build placeholder values for a booking.
	 *
	 * @param array<string, mixed> $booking       Booking.
	 * @param Event|null           $event         Event.
	 * @param array<string, mixed> $attendee      Attendee.
	 * @param string               $status_label  Status label.
	 * @param string               $ticket_name   Ticket name.
	 * @return array<string, string>
	 */
	private static function placeholders( array $booking, ?Event $event, array $attendee, string $status_label, string $ticket_name ): array {
		$event_id = $event ? $event->id : (int) ( $booking['event_id'] ?? 0 );
		$venue    = $event && $event->venue_id ? (string) get_the_title( $event->venue_id ) : '';

		return [
			'{event}'         => $event ? $event->title : '',
			'{event_url}'     => $event_id > 0 ? (string) get_permalink( $event_id ) : '',
			'{date}'          => $event ? Event_Format::start( $event ) : '',
			'{event_end}'     => $event ? Event_Format::end( $event ) : '',
			'{timezone}'      => $event ? $event->timezone : '',
			'{venue}'         => $venue,
			'{venue_address}' => $event && $event->venue_id ? (string) get_post_meta( $event->venue_id, '_reevents_address', true ) : '',
			'{ticket}'        => $ticket_name,
			'{reference}'     => (string) ( $booking['reference'] ?? '' ),
			'{manage_url}'    => Booking_Access::manage_url( $booking ),
			'{name}'          => (string) ( $attendee['name'] ?? '' ),
			'{email}'         => (string) ( $attendee['email'] ?? '' ),
			'{quantity}'      => (string) ( $booking['quantity'] ?? 1 ),
			'{total}'         => self::total_label( $booking ),
			'{calendar_url}'  => $event_id > 0 ? (string) add_query_arg( 'reevents_ical', (string) $event_id, home_url( '/' ) ) : '',
			'{site}'          => wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
			'{site_url}'      => home_url( '/' ),
			'{status}'        => (string) ( $booking['status'] ?? 'pending' ),
			'{status_label}'  => $status_label,
		];
	}

	/**
	 * Build the generated details rows.
	 *
	 * @param array<string, mixed> $booking      Booking.
	 * @param Event|null           $event        Event.
	 * @param array<int, mixed>    $attendees    Attendees.
	 * @param string               $status_label Status label.
	 * @param string               $type         admin or attendee.
	 * @param string               $ticket_name  Ticket name.
	 * @return array<int, array{label:string,value:string}>
	 */
	private static function details( array $booking, ?Event $event, array $attendees, string $status_label, string $type, string $ticket_name ): array {
		$rows = [];

		if ( $event ) {
			$rows[] = [
				'label' => __( 'Event', 're-events' ),
				'value' => $event->title,
			];
			$rows[] = [
				'label' => __( 'When', 're-events' ),
				'value' => Event_Format::start( $event ),
			];

			$end = Event_Format::end( $event );

			if ( '' !== $end ) {
				$rows[] = [
					'label' => __( 'Ends', 're-events' ),
					'value' => $end,
				];
			}

			if ( '' !== $event->timezone ) {
				$rows[] = [
					'label' => __( 'Time zone', 're-events' ),
					'value' => $event->timezone,
				];
			}

			if ( $event->venue_id ) {
				$rows[] = [
					'label' => __( 'Venue', 're-events' ),
					'value' => (string) get_the_title( $event->venue_id ),
				];
				$rows[] = [
					'label' => __( 'Address', 're-events' ),
					'value' => (string) get_post_meta( $event->venue_id, '_reevents_address', true ),
				];
			}
		}

		if ( '' !== $ticket_name ) {
			$rows[] = [
				'label' => __( 'Ticket', 're-events' ),
				'value' => $ticket_name,
			];
		}

		$rows[] = [
			'label' => __( 'Seats', 're-events' ),
			'value' => (string) ( $booking['quantity'] ?? 1 ),
		];
		$rows[] = [
			'label' => __( 'Total', 're-events' ),
			'value' => self::total_label( $booking ),
		];
		$rows[] = [
			'label' => __( 'Reference', 're-events' ),
			'value' => (string) ( $booking['reference'] ?? '' ),
		];
		$rows[] = [
			'label' => __( 'Status', 're-events' ),
			'value' => $status_label,
		];

		if ( $attendees ) {
			$list  = 'admin' === $type ? $attendees : [ $attendees[0] ];
			$lines = [];

			foreach ( $list as $attendee ) {
				if ( ! is_array( $attendee ) ) {
					continue;
				}

				$name  = (string) ( $attendee['name'] ?? '' );
				$email = (string) ( $attendee['email'] ?? '' );
				$line  = '' !== $name && '' !== $email ? $name . ' <' . $email . '>' : ( $name . $email );

				if ( '' !== $line ) {
					$lines[] = $line;
				}
			}

			if ( $lines ) {
				$rows[] = [
					'label' => 'admin' === $type ? __( 'Attendees', 're-events' ) : __( 'Attendee', 're-events' ),
					'value' => implode(
						'
',
						$lines
					),
				];
			}
		}

		return array_values(
			array_filter(
				$rows,
				static fn( array $row ): bool => '' !== trim( (string) $row['value'] )
			)
		);
	}

	/**
	 * Format the details rows as plain text.
	 *
	 * @param array<int, array{label:string,value:string}> $details Details.
	 */
	private static function details_text( array $details ): string {
		$lines = [];

		foreach ( $details as $row ) {
			$lines[] = $row['label'] . ': ' . str_replace( [ "\r\n", "\r" ], "\n", $row['value'] );
		}

		return implode( "\n", $lines );
	}

	/**
	 * Build sample data for test and preview mails.
	 *
	 * @param int $event_id Event ID, or 0 for sample data.
	 * @return array{array<string, mixed>, Event, array<int, array<string, string>>}
	 */
	private static function sample_context( int $event_id ): array {
		$event = $event_id > 0 ? ( new Event_Repository() )->find( $event_id ) : null;

		if ( ! $event ) {
			$event = new Event(
				title: __( 'Sample Event', 're-events' ),
				start_utc: gmdate( 'Y-m-d H:i:s', strtotime( '+7 days' ) ),
				end_utc: gmdate( 'Y-m-d H:i:s', strtotime( '+7 days +1 hour' ) ),
				timezone: 'UTC',
			);
		}

		$booking = [
			'event_id'    => $event->id,
			'reference'   => 'SAMPLE-123',
			'quantity'    => 2,
			'total_minor' => 5000,
			'currency'    => 'USD',
			'status'      => 'confirmed',
		];

		$attendees = [
			[
				'name'  => __( 'Sample Attendee', 're-events' ),
				'email' => 'attendee@example.com',
			],
		];

		return [ $booking, $event, $attendees ];
	}

	/**
	 * Read a ticket name.
	 *
	 * @param array<string, mixed> $booking Booking.
	 */
	private static function ticket_name( array $booking ): string {
		$ticket_id = (int) ( $booking['ticket_id'] ?? 0 );

		if ( $ticket_id <= 0 ) {
			return '';
		}

		$ticket = ( new Ticket_Repository() )->find( $ticket_id );

		return (string) ( $ticket['name'] ?? '' );
	}

	/**
	 * Format a booking total.
	 *
	 * @param array<string, mixed> $booking Booking.
	 */
	private static function total_label( array $booking ): string {
		$total = (int) ( $booking['total_minor'] ?? 0 );

		return $total > 0 ? Currency::format( $total, (string) ( $booking['currency'] ?? '' ) ) : __( 'Free', 're-events' );
	}

	/**
	 * Translate a booking status.
	 *
	 * @param string $status Status slug.
	 */
	private static function status_label( string $status ): string {
		switch ( $status ) {
			case 'confirmed':
				return __( 'Confirmed', 're-events' );
			case 'cancelled':
				return __( 'Cancelled', 're-events' );
			case 'rejected':
				return __( 'Rejected', 're-events' );
			default:
				return __( 'Pending', 're-events' );
		}
	}

	/**
	 * Admin recipients, falling back to the site admin address.
	 *
	 * @return string[]
	 */
	private static function admin_recipients(): array {
		$raw      = (string) Settings::get( 'email_admin_recipients', '' );
		$parts    = preg_split( '/[,;\s]+/', $raw );
		$emails   = array_filter( array_map( 'sanitize_email', is_array( $parts ) ? $parts : [] ), 'is_email' );
		$fallback = sanitize_email( (string) get_option( 'admin_email' ) );

		return $emails ? array_values( $emails ) : ( is_email( $fallback ) ? [ $fallback ] : [] );
	}

	/**
	 * Build sender and reply-to headers.
	 *
	 * @return string[]
	 */
	private static function headers(): array {
		$headers = [];
		$from    = sanitize_email( (string) Settings::get( 'email_from_address', '' ) );

		if ( is_email( $from ) ) {
			$name      = str_replace( [ '<', '>', '"', ',', ';' ], '', sanitize_text_field( (string) Settings::get( 'email_from_name', '' ) ) );
			$headers[] = 'From: ' . ( '' !== $name ? $name . ' <' . $from . '>' : $from );
		}

		$reply = sanitize_email( (string) Settings::get( 'email_reply_to', '' ) );

		if ( is_email( $reply ) ) {
			$headers[] = 'Reply-To: ' . $reply;
		}

		return $headers;
	}

	/**
	 * Read an email template, falling back to the global setting and default.
	 *
	 * @param string $key      Setting key.
	 * @param int    $event_id Event ID for per-event overrides.
	 */
	private static function template( string $key, int $event_id = 0 ): string {
		if ( $event_id > 0 ) {
			$override = (string) get_post_meta( $event_id, '_reevents_' . $key, true );

			if ( '' !== $override ) {
				return $override;
			}
		}

		$defaults = Settings::defaults();
		$value    = (string) Settings::get( $key, $defaults[ $key ] ?? '' );

		return '' !== $value ? $value : (string) ( $defaults[ $key ] ?? '' );
	}
}

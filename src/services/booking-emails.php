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
use ReEvents\Domain\Event;
use ReEvents\Render\Event_Format;

/**
 * Sends booking notifications.
 */
final class Booking_Emails {

	/**
	 * Hook into WordPress.
	 */
	public static function register(): void {
		add_action( 'reevents_booking_created', [ self::class, 'send' ], 10, 1 );
		add_action( 'reevents_booking_status_changed', [ self::class, 'on_status_change' ], 10, 2 );
	}

	/**
	 * Send emails for a new booking.
	 *
	 * @param int  $booking_id   Booking ID.
	 * @param bool $notify_admin Send the admin copy.
	 */
	public static function send( int $booking_id, bool $notify_admin = true ): void {
		$repo    = new Booking_Repository();
		$booking = $repo->find( $booking_id );

		if ( ! $booking ) {
			return;
		}

		$event     = ( new Event_Repository() )->find( (int) $booking['event_id'] );
		$attendees = $repo->attendees( $booking_id );

		if ( $notify_admin ) {
			$admin = self::compose( $booking, $event, $attendees, 'admin' );
			wp_mail( (string) get_option( 'admin_email' ), $admin['subject'], $admin['body'] );
		}

		foreach ( $attendees as $attendee ) {
			if ( ! is_email( (string) $attendee['email'] ) ) {
				continue;
			}

			$mail = self::compose( $booking, $event, [ $attendee ], 'attendee' );
			wp_mail( (string) $attendee['email'], $mail['subject'], $mail['body'] );
		}
	}

	/**
	 * Send a confirmation when a booking becomes confirmed.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $status     New status.
	 */
	public static function on_status_change( int $booking_id, string $status ): void {
		if ( 'confirmed' === $status ) {
			self::send( $booking_id );
		}
	}

	/**
	 * Build an email.
	 *
	 * @param array<string, mixed> $booking   Booking.
	 * @param Event|null           $event     Event.
	 * @param array<int, mixed>    $attendees Attendees.
	 * @param string               $type      admin or attendee.
	 * @return array{subject:string,body:string}
	 */
	public static function compose( array $booking, ?Event $event, array $attendees, string $type ): array {
		$attendee     = isset( $attendees[0] ) && is_array( $attendees[0] ) ? $attendees[0] : [];
		$total        = (int) ( $booking['total_minor'] ?? 0 );
		$placeholders = [
			'{event}'     => $event ? $event->title : '',
			'{date}'      => $event ? Event_Format::start( $event ) : '',
			'{venue}'     => $event && $event->venue_id ? (string) get_the_title( $event->venue_id ) : '',
			'{reference}' => (string) ( $booking['reference'] ?? '' ),
			'{name}'      => (string) ( $attendee['name'] ?? '' ),
			'{email}'     => (string) ( $attendee['email'] ?? '' ),
			'{quantity}'  => (string) ( $booking['quantity'] ?? 1 ),
			'{total}'     => $total > 0 ? Currency::format( $total, (string) ( $booking['currency'] ?? '' ) ) : __( 'Free', 're-events' ),
			'{site}'      => wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
		];

		$status = (string) ( $booking['status'] ?? 'pending' );

		$placeholders['{status}'] = $status;

		$event_id = $event ? $event->id : 0;

		if ( 'admin' === $type ) {
			$subject = self::template( 'email_admin_subject', $event_id );
			$body    = self::template( 'email_admin_body', $event_id );
		} else {
			$subject = self::template( 'email_attendee_subject', $event_id );
			$body    = self::template( 'email_attendee_body', $event_id );
		}

		return [
			'subject' => strtr( $subject, $placeholders ),
			'body'    => strtr( $body, $placeholders ),
		];
	}

	/**
	 * Send a template with sample values.
	 *
	 * @param string $type  admin or attendee.
	 * @param string $email Recipient.
	 */
	public static function send_test( string $type, string $email ): bool {
		$type         = 'attendee' === $type ? 'attendee' : 'admin';
		$placeholders = [
			'{event}'     => __( 'Sample Event', 're-events' ),
			'{date}'      => __( 'January 1, 2026 at 7:00 pm', 're-events' ),
			'{venue}'     => __( 'Sample Venue', 're-events' ),
			'{reference}' => 'SAMPLE-123',
			'{name}'      => __( 'Sample Attendee', 're-events' ),
			'{email}'     => 'attendee@example.com',
			'{quantity}'  => '2',
			'{total}'     => '50.00 USD',
			'{site}'      => wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
			'{status}'    => 'confirmed',
		];

		$subject_key = 'attendee' === $type ? 'email_attendee_subject' : 'email_admin_subject';
		$body_key    = 'attendee' === $type ? 'email_attendee_body' : 'email_admin_body';

		return wp_mail(
			$email,
			strtr( self::template( $subject_key ), $placeholders ),
			strtr( self::template( $body_key ), $placeholders )
		);
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

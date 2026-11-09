<?php
/**
 * Booking emails.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Services;

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
			'{total}'     => $total > 0 ? number_format_i18n( $total / 100, 2 ) . ' ' . (string) ( $booking['currency'] ?? '' ) : __( 'Free', 're-events' ),
			'{site}'      => wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
		];

		$status = (string) ( $booking['status'] ?? 'pending' );

		if ( 'admin' === $type ) {
			$subject = sprintf(
				/* translators: %s: booking reference. */
				__( '[%1$s] New booking %2$s', 're-events' ),
				$placeholders['{site}'],
				$placeholders['{reference}']
			);
			$body = __( "A new booking was created.\n\nEvent: {event}\nWhen: {date}\nVenue: {venue}\nReference: {reference}\nAttendee: {name} <{email}>\nSeats: {quantity}\nTotal: {total}", 're-events' );
		} else {
			$subject = 'confirmed' === $status
				? sprintf(
					/* translators: %s: event title. */
					__( 'Booking confirmed: %s', 're-events' ),
					$placeholders['{event}']
				)
				: sprintf(
					/* translators: %s: event title. */
					__( 'Booking received: %s', 're-events' ),
					$placeholders['{event}']
				);
			$body = __( "Hello {name},\n\nYour booking is {status}.\n\nEvent: {event}\nWhen: {date}\nVenue: {venue}\nReference: {reference}\nSeats: {quantity}\nTotal: {total}", 're-events' );
			$body = str_replace( '{status}', $status, $body );
		}

		return [
			'subject' => strtr( $subject, $placeholders ),
			'body'    => strtr( $body, $placeholders ),
		];
	}
}

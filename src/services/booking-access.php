<?php
/**
 * Booking access helpers.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Services;

use ReEvents\Admin\Settings;
use ReEvents\Data\Booking_Repository;

/**
 * Checks who may read or change a booking.
 */
final class Booking_Access {

	/**
	 * Signed key for one booking.
	 *
	 * @param array<string, mixed> $booking Booking.
	 */
	public static function key( array $booking ): string {
		return hash_hmac(
			'sha256',
			(int) ( $booking['id'] ?? 0 ) . '|' . (string) ( $booking['reference'] ?? '' ),
			wp_salt( 'auth' )
		);
	}

	/**
	 * Compare a signed key.
	 *
	 * @param array<string, mixed> $booking Booking.
	 * @param string               $key     Supplied key.
	 */
	public static function verify_key( array $booking, string $key ): bool {
		return '' !== $key && hash_equals( self::key( $booking ), $key );
	}

	/**
	 * Whether the current user owns the booking.
	 *
	 * @param array<string, mixed> $booking Booking.
	 */
	public static function is_owner( array $booking ): bool {
		$user_id = get_current_user_id();

		return $user_id > 0 && (int) ( $booking['user_id'] ?? 0 ) === $user_id;
	}

	/**
	 * Whether an email matches one of the booking attendees.
	 *
	 * @param array<string, mixed> $booking Booking.
	 * @param string               $email   Email.
	 */
	public static function email_matches( array $booking, string $email ): bool {
		$email = sanitize_email( $email );

		if ( '' === $email ) {
			return false;
		}

		foreach ( ( new Booking_Repository() )->attendees( (int) ( $booking['id'] ?? 0 ) ) as $attendee ) {
			if ( '' !== (string) $attendee['email'] && 0 === strcasecmp( (string) $attendee['email'], $email ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the credentials may read or change the booking.
	 *
	 * @param array<string, mixed> $booking     Booking.
	 * @param array<string, mixed> $credentials Keys: key, reference, email.
	 */
	public static function authorize( array $booking, array $credentials ): bool {
		if ( self::is_owner( $booking ) ) {
			return true;
		}

		if ( self::verify_key( $booking, (string) ( $credentials['key'] ?? '' ) ) ) {
			return true;
		}

		$reference = (string) ( $credentials['reference'] ?? '' );

		if ( '' === $reference || ! hash_equals( (string) ( $booking['reference'] ?? '' ), $reference ) ) {
			return false;
		}

		return self::email_matches( $booking, (string) ( $credentials['email'] ?? '' ) );
	}

	/**
	 * Find a booking for a guest lookup.
	 *
	 * @param string $reference Booking reference.
	 * @param string $email     Attendee email.
	 * @return array<string, mixed>|null
	 */
	public static function lookup( string $reference, string $email ): ?array {
		if ( ! Settings::get( 'self_service_guest', true ) ) {
			return null;
		}

		$booking = ( new Booking_Repository() )->find_by_reference( $reference );

		if ( ! $booking || ! self::email_matches( $booking, $email ) ) {
			return null;
		}

		return $booking;
	}

	/**
	 * Page URL for the signed management link.
	 *
	 * @param array<string, mixed> $booking Booking.
	 */
	public static function manage_url( array $booking ): string {
		$page = (string) Settings::get( 'self_service_page', '' );

		if ( '' === $page || ! Settings::get( 'self_service', true ) ) {
			return '';
		}

		return add_query_arg(
			[
				'reevents_booking'   => (int) ( $booking['id'] ?? 0 ),
				'reevents_reference' => (string) ( $booking['reference'] ?? '' ),
				'reevents_key'       => self::key( $booking ),
			],
			$page
		);
	}
}

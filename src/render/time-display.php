<?php
/**
 * Time display resolver.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Render;

use DateTimeZone;
use Exception;

/**
 * Resolves the timezone used to display event times.
 */
final class Time_Display {

	/**
	 * Resolve the display timezone.
	 *
	 * @param string $event_timezone Event IANA timezone.
	 */
	public static function timezone( string $event_timezone ): DateTimeZone {
		$mode = self::mode();

		if ( 'site' === $mode ) {
			return wp_timezone();
		}

		if ( 'visitor' === $mode ) {
			return self::visitor_timezone();
		}

		return self::safe_timezone( $event_timezone );
	}

	/**
	 * Current display mode.
	 */
	public static function mode(): string {
		$settings = get_option( 'reevents_settings', [] );
		$mode     = is_array( $settings ) && ! empty( $settings['time_display'] ) ? (string) $settings['time_display'] : 'event';

		return in_array( $mode, [ 'site', 'event', 'visitor' ], true ) ? $mode : 'event';
	}

	/**
	 * Visitor timezone from the preference cookie.
	 */
	private static function visitor_timezone(): DateTimeZone {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only preference cookie.
		$cookie = isset( $_COOKIE['reevents_timezone'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['reevents_timezone'] ) ) : '';

		if ( '' !== $cookie && in_array( $cookie, timezone_identifiers_list(), true ) ) {
			return self::safe_timezone( $cookie );
		}

		return wp_timezone();
	}

	/**
	 * Get a timezone, falling back to UTC.
	 *
	 * @param string $name IANA timezone.
	 */
	private static function safe_timezone( string $name ): DateTimeZone {
		try {
			return new DateTimeZone( $name ? $name : 'UTC' );
		} catch ( Exception ) {
			return new DateTimeZone( 'UTC' );
		}
	}
}

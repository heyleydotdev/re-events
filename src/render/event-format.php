<?php
/**
 * Event formatting helpers.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Render;

use DateTimeImmutable;
use DateTimeZone;
use ReEvents\Domain\Event;

/**
 * Formats event dates for templates.
 */
final class Event_Format {

	/**
	 * Format the event start.
	 *
	 * @param Event $event        Event.
	 * @param bool  $include_time Include the time.
	 */
	public static function start( Event $event, bool $include_time = true ): string {
		return self::format( $event->start_utc, $event->timezone, $event->all_day, $include_time );
	}

	/**
	 * Format the event end.
	 *
	 * @param Event $event        Event.
	 * @param bool  $include_time Include the time.
	 */
	public static function end( Event $event, bool $include_time = true ): string {
		return self::format( $event->end_utc, $event->timezone, $event->all_day, $include_time );
	}

	/**
	 * Format a UTC datetime.
	 *
	 * @param string $utc          UTC datetime.
	 * @param string $timezone     IANA timezone.
	 * @param bool   $all_day      All-day flag.
	 * @param bool   $include_time Include the time.
	 */
	private static function format( string $utc, string $timezone, bool $all_day, bool $include_time ): string {
		if ( '' === $utc ) {
			return '';
		}

		$date = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $utc, new DateTimeZone( 'UTC' ) );

		if ( ! $date instanceof DateTimeImmutable ) {
			return '';
		}

		$settings    = get_option( 'reevents_settings', [] );
		$date_format = is_array( $settings ) && ! empty( $settings['date_format'] ) ? (string) $settings['date_format'] : (string) get_option( 'date_format' );
		$time_format = is_array( $settings ) && ! empty( $settings['time_format'] ) ? (string) $settings['time_format'] : (string) get_option( 'time_format' );
		$format      = $date_format;

		if ( $include_time && ! $all_day ) {
			$format .= ' ' . $time_format;
		}

		$formatted = wp_date( $format, $date->getTimestamp(), Time_Display::timezone( $timezone ) );

		return false !== $formatted ? $formatted : '';
	}
}

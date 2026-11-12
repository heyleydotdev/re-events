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
	 * @param Event  $event        Event.
	 * @param bool   $include_time Include the time.
	 * @param string $date_format  Date format override.
	 * @param string $time_format  Time format override.
	 */
	public static function start( Event $event, bool $include_time = true, string $date_format = '', string $time_format = '' ): string {
		return self::format( $event->start_utc, $event->timezone, $event->all_day, $include_time, $date_format, $time_format );
	}

	/**
	 * Format the event end.
	 *
	 * @param Event  $event        Event.
	 * @param bool   $include_time Include the time.
	 * @param string $date_format  Date format override.
	 * @param string $time_format  Time format override.
	 */
	public static function end( Event $event, bool $include_time = true, string $date_format = '', string $time_format = '' ): string {
		return self::format( $event->end_utc, $event->timezone, $event->all_day, $include_time, $date_format, $time_format );
	}

	/**
	 * Format only the start time. Empty for all-day events.
	 *
	 * @param Event  $event       Event.
	 * @param string $time_format Time format override.
	 */
	public static function start_time( Event $event, string $time_format = '' ): string {
		return self::time( $event->start_utc, $event->timezone, $event->all_day, $time_format );
	}

	/**
	 * Format only the end time. Empty for all-day events.
	 *
	 * @param Event  $event       Event.
	 * @param string $time_format Time format override.
	 */
	public static function end_time( Event $event, string $time_format = '' ): string {
		return self::time( $event->end_utc, $event->timezone, $event->all_day, $time_format );
	}

	/**
	 * Format an occurrence row start.
	 *
	 * @param array<string, mixed> $row         Occurrence row.
	 * @param string               $date_format Date format override.
	 * @param string               $time_format Time format override.
	 */
	public static function occurrence_start( array $row, string $date_format = '', string $time_format = '' ): string {
		$event = new Event(
			0,
			'',
			(string) ( $row['start_utc'] ?? '' ),
			(string) ( $row['end_utc'] ?? '' ),
			(string) ( $row['timezone'] ?? 'UTC' ),
			! empty( $row['all_day'] )
		);

		return self::start( $event, true, $date_format, $time_format );
	}

	/**
	 * Whether an event is currently running.
	 *
	 * @param Event $event Event.
	 */
	public static function is_ongoing( Event $event ): bool {
		if ( '' === $event->start_utc || '' === $event->end_utc ) {
			return false;
		}

		$now = current_time( 'mysql', true );

		return $event->start_utc <= $now && $event->end_utc >= $now;
	}

	/**
	 * Format a UTC datetime.
	 *
	 * @param string $utc          UTC datetime.
	 * @param string $timezone     IANA timezone.
	 * @param bool   $all_day      All-day flag.
	 * @param bool   $include_time Include the time.
	 * @param string $date_format  Date format override.
	 * @param string $time_format  Time format override.
	 */
	private static function format( string $utc, string $timezone, bool $all_day, bool $include_time, string $date_format = '', string $time_format = '' ): string {
		if ( '' === $utc ) {
			return '';
		}

		$date = self::parse( $utc );

		if ( ! $date ) {
			return '';
		}

		$format = '' !== $date_format ? $date_format : self::date_format();

		if ( $include_time && ! $all_day ) {
			$format .= ' ' . ( '' !== $time_format ? $time_format : self::time_format() );
		}

		$formatted = wp_date( $format, $date->getTimestamp(), Time_Display::timezone( $timezone ) );

		return false !== $formatted ? $formatted : '';
	}

	/**
	 * Format only a UTC time.
	 *
	 * @param string $utc      UTC datetime.
	 * @param string $timezone IANA timezone.
	 * @param bool   $all_day     All-day flag.
	 * @param string $time_format Time format override.
	 */
	private static function time( string $utc, string $timezone, bool $all_day, string $time_format = '' ): string {
		if ( '' === $utc || $all_day ) {
			return '';
		}

		$date = self::parse( $utc );

		if ( ! $date ) {
			return '';
		}

		$formatted = wp_date( '' !== $time_format ? $time_format : self::time_format(), $date->getTimestamp(), Time_Display::timezone( $timezone ) );

		return false !== $formatted ? $formatted : '';
	}

	/**
	 * Parse a UTC datetime.
	 *
	 * @param string $utc UTC datetime.
	 */
	private static function parse( string $utc ): ?DateTimeImmutable {
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $utc, new DateTimeZone( 'UTC' ) );

		return $date instanceof DateTimeImmutable ? $date : null;
	}

	/**
	 * Configured date format.
	 */
	public static function date_format(): string {
		$settings = get_option( 'reevents_settings', [] );

		return is_array( $settings ) && ! empty( $settings['date_format'] ) ? (string) $settings['date_format'] : (string) get_option( 'date_format' );
	}

	/**
	 * Configured time format.
	 */
	public static function time_format(): string {
		$settings = get_option( 'reevents_settings', [] );

		return is_array( $settings ) && ! empty( $settings['time_format'] ) ? (string) $settings['time_format'] : (string) get_option( 'time_format' );
	}
}

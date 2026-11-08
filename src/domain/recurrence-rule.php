<?php
/**
 * Recurrence rule helpers.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Domain;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Builds and parses the RRULE subset used by the event editor.
 */
final class Recurrence_Rule {

	/**
	 * Supported weekdays.
	 */
	public const DAYS = [ 'MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU' ];

	/**
	 * Build an RRULE from editor fields.
	 *
	 * @param array<string, mixed> $args Editor values.
	 */
	public static function build( array $args ): string {
		$freq = strtolower( trim( (string) ( $args['freq'] ?? 'none' ) ) );

		if ( ! in_array( $freq, [ 'daily', 'weekly', 'monthly', 'yearly' ], true ) ) {
			return '';
		}

		$parts    = [ 'FREQ=' . strtoupper( $freq ) ];
		$interval = max( 1, (int) ( $args['interval'] ?? 1 ) );

		if ( $interval > 1 ) {
			$parts[] = 'INTERVAL=' . $interval;
		}

		if ( 'weekly' === $freq && ! empty( $args['weekdays'] ) && is_array( $args['weekdays'] ) ) {
			$days = array_values(
				array_intersect(
					self::DAYS,
					array_map( 'strtoupper', array_map( 'strval', $args['weekdays'] ) )
				)
			);

			if ( $days ) {
				$parts[] = 'BYDAY=' . implode( ',', $days );
			}
		}

		if ( 'monthly' === $freq && ! empty( $args['monthday'] ) ) {
			$parts[] = 'BYMONTHDAY=' . min( 31, max( 1, (int) $args['monthday'] ) );
		}

		$end = strtolower( trim( (string) ( $args['end'] ?? 'never' ) ) );

		if ( 'until' === $end && ! empty( $args['until'] ) ) {
			$until = self::format_until( (string) $args['until'] );

			if ( '' !== $until ) {
				$parts[] = 'UNTIL=' . $until;
			}
		} elseif ( 'count' === $end && ! empty( $args['count'] ) ) {
			$parts[] = 'COUNT=' . max( 1, (int) $args['count'] );
		}

		return implode( ';', $parts );
	}

	/**
	 * Parse an RRULE into editor fields.
	 *
	 * @param string $rrule RRULE string.
	 * @return array{freq:string,interval:int,weekdays:string[],monthday:int,end:string,until:string,count:int}
	 */
	public static function parse( string $rrule ): array {
		$result = [
			'freq'     => 'none',
			'interval' => 1,
			'weekdays' => [],
			'monthday' => 0,
			'end'      => 'never',
			'until'    => '',
			'count'    => 0,
		];

		if ( '' === $rrule ) {
			return $result;
		}

		$parts = [];

		foreach ( explode( ';', $rrule ) as $part ) {
			$pair = explode( '=', $part, 2 );

			if ( 2 === count( $pair ) ) {
				$parts[ strtoupper( trim( $pair[0] ) ) ] = trim( $pair[1] );
			}
		}

		$freq = strtolower( $parts['FREQ'] ?? '' );

		if ( in_array( $freq, [ 'daily', 'weekly', 'monthly', 'yearly' ], true ) ) {
			$result['freq'] = $freq;
		}

		$result['interval'] = max( 1, (int) ( $parts['INTERVAL'] ?? 1 ) );

		if ( ! empty( $parts['BYDAY'] ) ) {
			$result['weekdays'] = array_values(
				array_intersect(
					self::DAYS,
					array_map( 'strtoupper', array_map( 'trim', explode( ',', $parts['BYDAY'] ) ) )
				)
			);
		}

		if ( ! empty( $parts['BYMONTHDAY'] ) ) {
			$result['monthday'] = min( 31, max( 1, (int) $parts['BYMONTHDAY'] ) );
		}

		if ( ! empty( $parts['UNTIL'] ) ) {
			$result['end']   = 'until';
			$result['until'] = self::parse_until( $parts['UNTIL'] );
		} elseif ( ! empty( $parts['COUNT'] ) ) {
			$result['end']   = 'count';
			$result['count'] = max( 1, (int) $parts['COUNT'] );
		}

		return $result;
	}

	/**
	 * Format a date as an RRULE UNTIL value.
	 *
	 * @param string $value Date (Y-m-d).
	 */
	private static function format_until( string $value ): string {
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', trim( $value ), new DateTimeZone( 'UTC' ) );

		return $date instanceof DateTimeImmutable ? $date->format( 'Ymd\T235959\Z' ) : '';
	}

	/**
	 * Parse an RRULE UNTIL value into a date.
	 *
	 * @param string $value UNTIL value.
	 */
	private static function parse_until( string $value ): string {
		foreach ( [ 'Ymd\THis\Z', 'Ymd\THis', 'Ymd' ] as $format ) {
			$date = DateTimeImmutable::createFromFormat( '!' . $format, $value, new DateTimeZone( 'UTC' ) );

			if ( $date instanceof DateTimeImmutable ) {
				return $date->format( 'Y-m-d' );
			}
		}

		return '';
	}
}

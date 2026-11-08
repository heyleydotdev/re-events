<?php
/**
 * Occurrence service.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Services;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use InvalidArgumentException;
use ReEvents\Data\Event_Repository;
use ReEvents\Data\Occurrence_Repository;
use ReEvents\Domain\Event;
use RRule\RRule;
use RRule\RSet;

/**
 * Expands and stores event occurrences.
 */
final class Occurrence_Service {

	/**
	 * Rebuild occurrences for an event.
	 *
	 * @param int $event_id Event ID.
	 */
	public static function sync( int $event_id ): void {
		$event = ( new Event_Repository() )->find( $event_id );
		$repo  = new Occurrence_Repository();

		if ( ! $event || '' === $event->start_utc ) {
			$repo->delete_for_event( $event_id );
			return;
		}

		$repo->replace_for_event( $event_id, self::expand( $event ) );
	}

	/**
	 * Expand an event into occurrence rows.
	 *
	 * @param Event $event Event.
	 * @param int   $limit Maximum occurrences.
	 * @return array<int, array{start_utc:string,end_utc:string,timezone:string,timeslot_key:string,capacity:int,status:string}>
	 */
	public static function expand( Event $event, int $limit = 500 ): array {
		$start = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $event->start_utc, new DateTimeZone( 'UTC' ) );

		if ( ! $start instanceof DateTimeImmutable ) {
			return [];
		}

		$timezone    = self::timezone( $event->timezone );
		$start_local = $start->setTimezone( $timezone );
		$end         = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $event->end_utc, new DateTimeZone( 'UTC' ) );
		$duration    = $end instanceof DateTimeImmutable ? max( 0, $end->getTimestamp() - $start->getTimestamp() ) : 0;

		if ( '' === $event->rrule ) {
			return [ self::row( $start_local, $duration, $event, $timezone ) ];
		}

		try {
			$rule = new RRule( $event->rrule, new DateTime( $start_local->format( 'Y-m-d H:i:s' ), $timezone ) );
			$set  = new RSet();
			$set->addRRule( $rule );

			foreach ( $event->exdates as $exdate ) {
				$date = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $exdate, new DateTimeZone( 'UTC' ) );

				if ( $date instanceof DateTimeImmutable ) {
					$set->addExDate( new DateTime( $date->setTimezone( $timezone )->format( 'Y-m-d H:i:s' ), $timezone ) );
				}
			}

			$occurrences = [];

			foreach ( $set->getOccurrencesBetween( $start_local, $start_local->modify( '+2 years' ), $limit ) as $date ) {
				$occurrences[] = self::row( $date, $duration, $event, $timezone );
			}

			return $occurrences;
		} catch ( InvalidArgumentException ) {
			return [ self::row( $start_local, $duration, $event, $timezone ) ];
		}
	}

	/**
	 * Build an occurrence row.
	 *
	 * @param DateTimeInterface $date     Occurrence start.
	 * @param int               $duration Duration in seconds.
	 * @param Event             $event    Event.
	 * @param DateTimeZone      $timezone Event timezone.
	 * @return array{start_utc:string,end_utc:string,timezone:string,timeslot_key:string,capacity:int,status:string}
	 */
	private static function row( DateTimeInterface $date, int $duration, Event $event, DateTimeZone $timezone ): array {
		$utc       = DateTimeImmutable::createFromInterface( $date )->setTimezone( new DateTimeZone( 'UTC' ) );
		$start_utc = $utc->format( 'Y-m-d H:i:s' );
		$end_utc   = gmdate( 'Y-m-d H:i:s', $utc->getTimestamp() + $duration );

		return [
			'start_utc'    => $start_utc,
			'end_utc'      => $end_utc,
			'timezone'     => $timezone->getName(),
			'timeslot_key' => '',
			'capacity'     => $event->capacity,
			'status'       => $event->status,
		];
	}

	/**
	 * Get a timezone, falling back to UTC.
	 *
	 * @param string $name IANA timezone.
	 */
	private static function timezone( string $name ): DateTimeZone {
		try {
			return new DateTimeZone( $name ? $name : 'UTC' );
		} catch ( Exception ) {
			return new DateTimeZone( 'UTC' );
		}
	}
}

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
use WP_Post;

/**
 * Expands and stores event occurrences.
 */
final class Occurrence_Service {

	/**
	 * Hook into WordPress.
	 */
	public static function register(): void {
		add_action( 'deleted_post', [ self::class, 'on_delete' ], 10, 2 );
		add_action( 'transition_post_status', [ self::class, 'on_status_change' ], 10, 3 );
	}

	/**
	 * Delete occurrences when an event is deleted.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public static function on_delete( int $post_id, WP_Post $post ): void {
		if ( 'reevents_event' !== $post->post_type ) {
			return;
		}

		( new Occurrence_Repository() )->delete_for_event( $post_id );
	}

	/**
	 * Rebuild or clear occurrences when an event changes status.
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Old status.
	 * @param WP_Post $post       Post object.
	 */
	public static function on_status_change( string $new_status, string $old_status, WP_Post $post ): void {
		if ( 'reevents_event' !== $post->post_type || $new_status === $old_status ) {
			return;
		}

		if ( 'publish' !== $new_status && 'publish' !== $old_status ) {
			return;
		}

		self::sync( $post->ID );
	}

	/**
	 * Rebuild occurrences for an event.
	 *
	 * @param int $event_id Event ID.
	 */
	public static function sync( int $event_id ): bool {
		$event = ( new Event_Repository() )->find( $event_id );
		$repo  = new Occurrence_Repository();

		if ( ! $event || 'publish' !== get_post_status( $event_id ) || '' === $event->start_utc ) {
			$repo->delete_for_event( $event_id );
			return true;
		}

		return $repo->replace_for_event( $event_id, self::expand( $event ) );
	}

	/**
	 * Expand an event into occurrence rows.
	 *
	 * @param Event $event Event.
	 * @param int   $limit Maximum occurrence dates.
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

		// shortcut: keep one year of past and future occurrences; extend with a cron when bookings need more history.
		$now           = new DateTimeImmutable( 'now', $timezone );
		$window_start  = $start_local < $now->modify( '-1 year' ) ? $now->modify( '-1 year' ) : $start_local;
		$window_end    = $now->modify( '+1 year' );
		$start_horizon = $start_local->modify( '+1 year' );

		if ( $start_horizon > $window_end ) {
			$window_end = $start_horizon;
		}

		$set      = new RSet();
		$has_rule = '' !== $event->rrule;

		if ( $has_rule ) {
			try {
				$set->addRRule( new RRule( $event->rrule, new DateTime( $start_local->format( 'Y-m-d H:i:s' ), $timezone ) ) );
			} catch ( InvalidArgumentException ) {
				$has_rule = false;
			}
		}

		if ( ! $has_rule ) {
			$set->addDate( new DateTime( $start_local->format( 'Y-m-d H:i:s' ), $timezone ) );
		}

		foreach ( $event->rdates as $rdate ) {
			$date = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $rdate, new DateTimeZone( 'UTC' ) );

			if ( $date instanceof DateTimeImmutable ) {
				$set->addDate( new DateTime( $date->setTimezone( $timezone )->format( 'Y-m-d H:i:s' ), $timezone ) );
			}
		}

		foreach ( $event->exdates as $exdate ) {
			$date = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $exdate, new DateTimeZone( 'UTC' ) );

			if ( $date instanceof DateTimeImmutable ) {
				$set->addExDate( new DateTime( $date->setTimezone( $timezone )->format( 'Y-m-d H:i:s' ), $timezone ) );
			}
		}

		$occurrences = [];

		foreach ( $set->getOccurrencesBetween( $window_start, $window_end, $limit ) as $date ) {
			if ( ! empty( $event->timeslots ) ) {
				foreach ( $event->timeslots as $slot ) {
					$occurrences[] = self::slot_row( $date, $slot, $event, $timezone );
				}
			} else {
				$occurrences[] = self::row( $date, $duration, $event, $timezone );
			}
		}

		return $occurrences;
	}

	/**
	 * Build a timeslot occurrence row.
	 *
	 * @param DateTimeInterface                           $date     Occurrence date.
	 * @param array{start:string,end:string,capacity:int} $slot Slot.
	 * @param Event                                       $event    Event.
	 * @param DateTimeZone                                $timezone Event timezone.
	 * @return array{start_utc:string,end_utc:string,timezone:string,timeslot_key:string,capacity:int,status:string}
	 */
	private static function slot_row( DateTimeInterface $date, array $slot, Event $event, DateTimeZone $timezone ): array {
		$local = DateTimeImmutable::createFromInterface( $date )->setTimezone( $timezone );
		$day   = $local->format( 'Y-m-d' );
		$start = new DateTime( $day . ' ' . $slot['start'], $timezone );
		$end   = new DateTime( $day . ' ' . $slot['end'], $timezone );

		if ( $end <= $start ) {
			$end->modify( '+1 day' );
		}

		$utc_start = ( new DateTimeImmutable( $start->format( 'Y-m-d H:i:s' ), $timezone ) )->setTimezone( new DateTimeZone( 'UTC' ) );
		$utc_end   = ( new DateTimeImmutable( $end->format( 'Y-m-d H:i:s' ), $timezone ) )->setTimezone( new DateTimeZone( 'UTC' ) );

		return [
			'start_utc'    => $utc_start->format( 'Y-m-d H:i:s' ),
			'end_utc'      => $utc_end->format( 'Y-m-d H:i:s' ),
			'timezone'     => $timezone->getName(),
			'timeslot_key' => $slot['start'] . '-' . $slot['end'],
			'capacity'     => (int) $slot['capacity'],
			'status'       => $event->status,
		];
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

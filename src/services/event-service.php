<?php
/**
 * Event service.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Services;

use ReEvents\Data\Event_Repository;
use ReEvents\Domain\Event;
use InvalidArgumentException;
use RRule\RRule;
use WP_Error;

/**
 * Validates, saves and expands events.
 */
final class Event_Service {

	/**
	 * Save an event and rebuild its occurrences.
	 *
	 * @param Event  $event       Event.
	 * @param string $post_status Status for a new event.
	 * @return int|WP_Error
	 */
	public static function save( Event $event, string $post_status = '' ) {
		$error = self::validate( $event );

		if ( is_wp_error( $error ) ) {
			return $error;
		}

		$is_new = 0 === $event->id;

		Occurrence_Service::defer( true );
		$result = ( new Event_Repository() )->save( $event, $post_status );
		Occurrence_Service::defer( false );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( ! Occurrence_Service::sync( (int) $result ) ) {
			if ( $is_new ) {
				( new Event_Repository() )->delete( (int) $result );
			}

			return new WP_Error( 'rest_event_occurrences_failed', __( 'The event was saved, but occurrences could not be rebuilt.', 're-events' ), [ 'status' => 500 ] );
		}

		return (int) $result;
	}

	/**
	 * Validate an event.
	 *
	 * @param Event $event Event.
	 * @return WP_Error|null
	 */
	public static function validate( Event $event ): ?WP_Error {
		if ( '' === $event->title ) {
			return new WP_Error( 'rest_event_invalid_title', __( 'An event title is required.', 're-events' ), [ 'status' => 400 ] );
		}

		if ( '' === $event->start_utc ) {
			return new WP_Error( 'rest_event_invalid_start', __( 'A valid start time is required.', 're-events' ), [ 'status' => 400 ] );
		}

		if ( '' !== $event->end_utc && $event->end_utc < $event->start_utc ) {
			return new WP_Error( 'rest_event_invalid_range', __( 'The end time must not be before the start time.', 're-events' ), [ 'status' => 400 ] );
		}

		if ( '' !== $event->rrule ) {
			try {
				new RRule( $event->rrule, new \DateTime( 'now', new \DateTimeZone( 'UTC' ) ) );
			} catch ( InvalidArgumentException ) {
				return new WP_Error( 'rest_event_invalid_rrule', __( 'The recurrence rule is invalid.', 're-events' ), [ 'status' => 400 ] );
			}
		}

		if ( $event->venue_id && 'reevents_venue' !== get_post_type( $event->venue_id ) ) {
			return new WP_Error( 'rest_event_invalid_venue', __( 'The venue does not exist.', 're-events' ), [ 'status' => 400 ] );
		}

		foreach ( $event->organizer_ids as $organizer_id ) {
			if ( 'reevents_organizer' !== get_post_type( $organizer_id ) ) {
				return new WP_Error( 'rest_event_invalid_organizer', __( 'An organizer does not exist.', 're-events' ), [ 'status' => 400 ] );
			}
		}

		return null;
	}
}

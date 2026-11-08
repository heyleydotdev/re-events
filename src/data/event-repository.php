<?php
/**
 * Event repository.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Data;

use ReEvents\Domain\Event;
use WP_Error;
use WP_Post;

/**
 * Reads and writes events.
 */
final class Event_Repository {

	/**
	 * Find an event by post ID.
	 *
	 * @param int $id Post ID.
	 */
	public function find( int $id ): ?Event {
		$post = get_post( $id );

		if ( ! $post instanceof WP_Post || 're_event' !== $post->post_type ) {
			return null;
		}

		return $this->hydrate( $post );
	}

	/**
	 * Save an event.
	 *
	 * @param Event $event Event data.
	 * @return int|WP_Error Post ID or error.
	 */
	public function save( Event $event ) {
		$post_status = 'publish';

		if ( $event->id ) {
			$existing_status = get_post_status( $event->id );

			if ( $existing_status ) {
				$post_status = $existing_status;
			}
		}

		$postarr = [
			'ID'          => $event->id,
			'post_type'   => 're_event',
			'post_title'  => $event->title,
			'post_status' => $post_status,
			'meta_input'  => $this->meta( $event ),
		];

		return $event->id ? wp_update_post( $postarr, true ) : wp_insert_post( $postarr, true );
	}

	/**
	 * Delete an event.
	 *
	 * @param int $id Post ID.
	 */
	public function delete( int $id ): bool {
		if ( 're_event' !== get_post_type( $id ) ) {
			return false;
		}

		return wp_delete_post( $id, true ) instanceof WP_Post;
	}

	/**
	 * Hydrate an event from a post.
	 *
	 * @param WP_Post $post Post object.
	 */
	private function hydrate( WP_Post $post ): Event {
		$exdates       = get_post_meta( $post->ID, '_re_exdates', true );
		$organizer_ids = get_post_meta( $post->ID, '_re_organizer_ids', true );

		return new Event(
			$post->ID,
			$post->post_title,
			(string) get_post_meta( $post->ID, '_re_start_utc', true ),
			(string) get_post_meta( $post->ID, '_re_end_utc', true ),
			(string) get_post_meta( $post->ID, '_re_timezone', true ),
			(bool) get_post_meta( $post->ID, '_re_all_day', true ),
			(string) get_post_meta( $post->ID, '_re_rrule', true ),
			is_array( $exdates ) ? array_values( array_map( 'strval', $exdates ) ) : [],
			(int) get_post_meta( $post->ID, '_re_venue_id', true ),
			is_array( $organizer_ids ) ? array_values( array_map( 'intval', $organizer_ids ) ) : [],
			(int) get_post_meta( $post->ID, '_re_capacity', true ),
			(string) get_post_meta( $post->ID, '_re_online_url', true ),
			(string) get_post_meta( $post->ID, '_re_status', true )
		);
	}

	/**
	 * Build meta input.
	 *
	 * @param Event $event Event data.
	 * @return array<string, mixed>
	 */
	private function meta( Event $event ): array {
		return [
			'_re_start_utc'     => $event->start_utc,
			'_re_end_utc'       => $event->end_utc,
			'_re_timezone'      => $event->timezone,
			'_re_all_day'       => $event->all_day,
			'_re_rrule'         => $event->rrule,
			'_re_exdates'       => $event->exdates,
			'_re_venue_id'      => $event->venue_id,
			'_re_organizer_ids' => $event->organizer_ids,
			'_re_capacity'      => $event->capacity,
			'_re_online_url'    => $event->online_url,
			'_re_status'        => $event->status,
		];
	}
}

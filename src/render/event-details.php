<?php
/**
 * Event details renderer.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Render;

use ReEvents\Data\Event_Repository;

/**
 * Renders a single event.
 */
final class Event_Details {

	/**
	 * Render event details.
	 *
	 * @param array<string, mixed> $args Widget arguments.
	 */
	public static function render( array $args ): string {
		$id = (int) ( $args['id'] ?? 0 );

		if ( ! $id ) {
			$id = (int) get_the_ID();
		}

		$event = ( new Event_Repository() )->find( $id );

		if ( ! $event ) {
			return '';
		}

		if ( 'publish' !== get_post_status( $id ) && ! current_user_can( 'read_post', $id ) ) {
			return '';
		}

		$post = get_post( $id );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core content filter.
		$content    = $post ? (string) apply_filters( 'the_content', $post->post_content ) : '';
		$venue      = $event->venue_id ? (string) get_the_title( $event->venue_id ) : '';
		$organizers = [];

		foreach ( $event->organizer_ids as $organizer_id ) {
			$title = (string) get_the_title( $organizer_id );

			if ( '' === $title ) {
				continue;
			}

			$types = get_the_terms( $organizer_id, 'reevents_organizer_type' );
			$names = [];

			foreach ( is_array( $types ) ? $types : [] as $term ) {
				$names[] = $term->name;
			}

			$organizers[] = [
				'name' => $title,
				'type' => implode( ', ', $names ),
			];
		}

		return Template::render(
			'event-details',
			[
				'event'      => $event,
				'content'    => $content,
				'venue'      => $venue,
				'organizers' => $organizers,
				'args'       => $args,
			]
		);
	}
}

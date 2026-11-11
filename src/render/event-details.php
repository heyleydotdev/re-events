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
		$content    = $post && ! post_password_required( $id ) ? (string) apply_filters( 'the_content', $post->post_content ) : '';
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

		$calendar_links = [];

		if ( 'cancelled' !== $event->status && ( ! isset( $args['show_calendar_links'] ) || ! empty( $args['show_calendar_links'] ) ) ) {
			$calendar_links = Calendar_Links::links(
				$event,
				[
					'google'    => (string) ( $args['google_label'] ?? __( 'Google', 're-events' ) ),
					'outlook'   => (string) ( $args['outlook_label'] ?? __( 'Outlook', 're-events' ) ),
					'office365' => (string) ( $args['office365_label'] ?? __( 'Office 365', 're-events' ) ),
					'ical'      => (string) ( $args['ical_label'] ?? __( 'Apple / iCal', 're-events' ) ),
				]
			);
		}

		return Template::render(
			'event-details',
			[
				'event'          => $event,
				'content'        => $content,
				'venue'          => $venue,
				'organizers'     => $organizers,
				'calendar_links' => $calendar_links,
				'args'           => $args,
			]
		);
	}
}

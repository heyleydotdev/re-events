<?php
/**
 * Event details renderer.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Render;

use ReEvents\Data\Event_Repository;
use ReEvents\Data\Ticket_Repository;

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

		if ( $event->venue_id ) {
			_prime_post_caches( [ $event->venue_id ], false, true );
		}

		if ( $event->organizer_ids ) {
			_prime_post_caches( $event->organizer_ids, false, true );
			update_object_term_cache( $event->organizer_ids, 'reevents_organizer_type' );
		}

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
				'url'  => 'publish' === get_post_status( $organizer_id ) ? (string) get_permalink( $organizer_id ) : '',
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

		$venue_address  = '';
		$venue_map      = '';
		$venue_map_html = '';

		if ( $event->venue_id ) {
			$lat           = (string) get_post_meta( $event->venue_id, '_reevents_lat', true );
			$lng           = (string) get_post_meta( $event->venue_id, '_reevents_lng', true );
			$venue_address = (string) get_post_meta( $event->venue_id, '_reevents_address', true );
			$venue_map     = Field::map_link( $lat, $lng );

			if ( ! empty( $args['show_venue_map'] ) && 'publish' === get_post_status( $event->venue_id ) && '' !== $lat && '' !== $lng ) {
				$venue_map_html = Map::render(
					[
						'venue'         => $event->venue_id,
						'limit'         => 1,
						'height'        => max( 200, (int) ( $args['venue_map_height'] ?? 320 ) ),
						'link'          => false,
						'show_address'  => ! empty( $args['show_venue_address'] ),
						'wrapper_class' => 'reevents-event-map',
					]
				);
			}
		}

		$summary = [];

		if ( ! empty( $args['show_price'] ) || ! empty( $args['show_seats_left'] ) ) {
			$summary = Ticket_Summary::from_tickets( ( new Ticket_Repository() )->for_event( $id, 'active' ) );
		}

		$categories = [];
		$tags       = [];

		if ( ! empty( $args['show_categories'] ) ) {
			update_object_term_cache( [ $id ], 'reevents_event_category' );
			$categories = self::term_names( $id, 'reevents_event_category' );
		}

		if ( ! empty( $args['show_tags'] ) ) {
			update_object_term_cache( [ $id ], 'reevents_event_tag' );
			$tags = self::term_names( $id, 'reevents_event_tag' );
		}

		$excerpt = ! empty( $args['show_excerpt'] ) ? (string) get_the_excerpt( $id ) : '';

		return Template::render(
			'event-details',
			[
				'event'          => $event,
				'content'        => $content,
				'venue'          => $venue,
				'venue_address'  => $venue_address,
				'venue_map'      => $venue_map,
				'venue_map_html' => $venue_map_html,
				'organizers'     => $organizers,
				'calendar_links' => $calendar_links,
				'summary'        => $summary,
				'categories'     => $categories,
				'tags'           => $tags,
				'excerpt'        => $excerpt,
				'args'           => $args,
			]
		);
	}

	/**
	 * Term names for one post.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy.
	 * @return string[]
	 */
	private static function term_names( int $post_id, string $taxonomy ): array {
		$terms = get_the_terms( $post_id, $taxonomy );
		$names = [];

		foreach ( is_array( $terms ) ? $terms : [] as $term ) {
			$names[] = (string) $term->name;
		}

		return $names;
	}
}

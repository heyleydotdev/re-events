<?php
/**
 * Events list renderer.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Render;

use ReEvents\Data\Event_Repository;
use ReEvents\Data\Ticket_Repository;
use ReEvents\Query\Event_Query;

/**
 * Renders a list of upcoming events.
 */
final class Events_List {

	/**
	 * Render the events list.
	 *
	 * @param array<string, mixed> $args Widget arguments.
	 */
	public static function render( array $args ): string {
		$requested = absint( $_GET['reevents_page'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public pagination.
		$page      = max( 1, $requested > 0 ? $requested : (int) ( $args['page'] ?? 1 ) );
		$venue     = $args['venue'] ?? 0;
		$organizer = $args['organizer'] ?? 0;

		if ( 'current' === $venue ) {
			$venue = 'reevents_venue' === get_post_type() ? (int) get_the_ID() : -1;
		}

		if ( 'current' === $organizer ) {
			$organizer = 'reevents_organizer' === get_post_type() ? (int) get_the_ID() : -1;
		}

		$result = Event_Query::query(
			[
				'per_page'  => max( 1, (int) ( $args['limit'] ?? 5 ) ),
				'page'      => $page,
				'search'    => (string) ( $args['search'] ?? '' ),
				'category'  => (int) ( $args['category'] ?? 0 ),
				'venue'     => (int) $venue,
				'organizer' => (int) $organizer,
				'upcoming'  => ! empty( $args['upcoming'] ),
				'from'      => (string) ( $args['from'] ?? '' ),
				'to'        => (string) ( $args['to'] ?? '' ),
			]
		);
		$repo   = new Event_Repository();
		$events = [];

		foreach ( $repo->find_many( $result['ids'] ) as $event ) {
			$events[] = $event;
		}

		$venue_ids = array_values( array_unique( array_filter( array_map( static fn( $event ): int => (int) $event->venue_id, $events ) ) ) );

		if ( $venue_ids ) {
			_prime_post_caches( $venue_ids, false, true );
		}

		$event_ids = array_map( static fn( $event ): int => (int) $event->id, $events );
		$tickets   = ( new Ticket_Repository() )->for_events( $event_ids, 'active' );
		$summaries = [];

		foreach ( $events as $event ) {
			$summaries[ $event->id ] = Ticket_Summary::from_tickets( $tickets[ $event->id ] ?? [] );
		}

		if ( $event_ids && ! empty( $args['show_categories'] ) ) {
			update_object_term_cache( $event_ids, 'reevents_event_category' );
		}

		$base_url = (string) remove_query_arg( 'reevents_page' );

		return Template::render(
			'events-list',
			[
				'events'    => $events,
				'summaries' => $summaries,
				'args'      => $args,
				'page'      => $page,
				'pages'     => (int) $result['pages'],
				'total'     => (int) $result['total'],
				'base_url'  => '' !== $base_url ? $base_url : home_url( '/' ),
			]
		);
	}
}

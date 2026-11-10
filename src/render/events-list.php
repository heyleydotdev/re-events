<?php
/**
 * Events list renderer.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Render;

use ReEvents\Data\Event_Repository;
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
		$result = Event_Query::query(
			[
				'per_page' => max( 1, (int) ( $args['limit'] ?? 5 ) ),
				'search'   => (string) ( $args['search'] ?? '' ),
				'category' => (int) ( $args['category'] ?? 0 ),
				'venue'    => (int) ( $args['venue'] ?? 0 ),
				'upcoming' => ! empty( $args['upcoming'] ),
				'from'     => (string) ( $args['from'] ?? '' ),
				'to'       => (string) ( $args['to'] ?? '' ),
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

		return Template::render(
			'events-list',
			[
				'events' => $events,
				'args'   => $args,
			]
		);
	}
}

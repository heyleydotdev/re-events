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

		foreach ( $result['ids'] as $id ) {
			$event = $repo->find( $id );

			if ( $event ) {
				$events[] = $event;
			}
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

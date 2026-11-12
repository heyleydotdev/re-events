<?php
/**
 * Events list renderer.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Render;

use ReEvents\Data\Event_Repository;
use ReEvents\Data\Meta;
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
		$paginate  = Pagination::enabled( $args );
		$param     = Pagination::param( $args, 'reevents_page_', 'reevents_page' );
		$page      = Pagination::page( $args, $param, $paginate );
		$search    = (string) ( $args['search'] ?? '' );
		$category  = (int) ( $args['category'] ?? 0 );
		$venue     = $args['venue'] ?? 0;
		$organizer = $args['organizer'] ?? 0;
		$from      = (string) ( $args['from'] ?? '' );
		$to        = (string) ( $args['to'] ?? '' );
		$upcoming  = ! empty( $args['upcoming'] );

		if ( ! empty( $args['use_query'] ) ) {
			if ( self::has_query_key( 'reevents_search' ) ) {
				$search = self::query_value( 'reevents_search' );
			}

			if ( self::has_query_key( 'reevents_category' ) ) {
				$category = absint( self::query_value( 'reevents_category' ) );
			}

			if ( self::has_query_key( 'reevents_venue_filter' ) ) {
				$venue = absint( self::query_value( 'reevents_venue_filter' ) );
			}

			if ( self::has_query_key( 'reevents_from' ) ) {
				$from = Meta::sanitize_date( self::query_value( 'reevents_from' ) );
			}

			if ( self::has_query_key( 'reevents_to' ) ) {
				$to = Meta::sanitize_date( self::query_value( 'reevents_to' ) );
			}

			if ( self::has_query_key( 'reevents_upcoming' ) ) {
				$upcoming = ! empty( self::query_value( 'reevents_upcoming' ) );
			}
		}

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
				'search'    => $search,
				'category'  => $category,
				'venue'     => (int) $venue,
				'organizer' => (int) $organizer,
				'upcoming'  => $upcoming,
				'from'      => $from,
				'to'        => $to,
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

		$base_url = (string) remove_query_arg( [ $param, 'reevents_page' ] );
		$base_url = '' !== $base_url ? $base_url : home_url( '/' );
		$pager    = $paginate ? Pagination::links( $param, $base_url, $page, (int) $result['pages'], __( 'Event pages', 're-events' ) ) : '';

		return Template::render(
			'events-list',
			[
				'events'     => $events,
				'summaries'  => $summaries,
				'args'       => $args,
				'pagination' => $pager,
			]
		);
	}

	/**
	 * Whether the current URL carries a usable search value.
	 *
	 * @param string $key Query argument.
	 */
	private static function has_query_key( string $key ): bool {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only search filters.
		return isset( $_GET[ $key ] ) && is_scalar( $_GET[ $key ] );
		// phpcs:enable
	}

	/**
	 * Read one sanitized search value.
	 *
	 * @param string $key Query argument.
	 */
	private static function query_value( string $key ): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only search filters, sanitized below.
		if ( ! isset( $_GET[ $key ] ) || ! is_scalar( $_GET[ $key ] ) ) {
			return '';
		}

		return sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
		// phpcs:enable
	}
}

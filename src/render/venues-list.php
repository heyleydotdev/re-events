<?php
/**
 * Venues list renderer.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Render;

use ReEvents\Data\Occurrence_Repository;
use WP_Query;

/**
 * Renders a list of venues.
 */
final class Venues_List {

	/**
	 * Render the venues list.
	 *
	 * @param array<string, mixed> $args Widget arguments.
	 */
	public static function render( array $args ): string {
		$paginate = Pagination::enabled( $args );
		$param    = Pagination::param( $args, 'reevents_venue_page_', 'reevents_venue_page' );
		$page     = Pagination::page( $args, $param, $paginate );
		$order    = 'DESC' === strtoupper( (string) ( $args['order'] ?? 'asc' ) ) ? 'DESC' : 'ASC';
		$orderby  = (string) ( $args['orderby'] ?? 'title' );
		$orderby  = in_array( $orderby, [ 'title', 'date', 'modified', 'menu_order', 'ID' ], true ) ? $orderby : 'title';
		$date_fmt = (string) ( $args['date_format'] ?? '' );
		$time_fmt = (string) ( $args['time_format'] ?? '' );
		$query    = [
			'post_type'      => 'reevents_venue',
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, (int) ( $args['limit'] ?? 10 ) ),
			'paged'          => $page,
			'no_found_rows'  => ! $paginate,
			's'              => sanitize_text_field( (string) ( $args['search'] ?? '' ) ),
			'orderby'        => $orderby,
			'order'          => $order,
		];

		if ( ! empty( $args['upcoming'] ) ) {
			$venue_ids         = ( new Occurrence_Repository() )->venue_ids_with_upcoming(
				current_time( 'mysql', true ),
				gmdate( 'Y-m-d H:i:s', strtotime( '+1 year' ) )
			);
			$query['post__in'] = $venue_ids ? $venue_ids : [ 0 ];
		}

		$query = new WP_Query( $query );
		$posts = $query->posts;
		$ids   = array_map( static fn( $post ): int => (int) $post->ID, $posts );

		if ( $ids ) {
			_prime_post_caches( $ids, false, true );
		}

		$next_events = [];

		if ( $ids && ! empty( $args['show_next_event'] ) ) {
			$next_events = ( new Occurrence_Repository() )->next_events_for_venues( $ids, current_time( 'mysql', true ) );
			$event_ids   = array_values( array_filter( array_map( static fn( $row ): int => (int) $row['event_id'], $next_events ) ) );

			if ( $event_ids ) {
				_prime_post_caches( $event_ids, false, false );
			}
		}

		$venues = [];

		foreach ( $posts as $post ) {
			$venue_id   = (int) $post->ID;
			$next_event = null;

			if ( isset( $next_events[ $venue_id ] ) ) {
				$row        = $next_events[ $venue_id ];
				$next_event = [
					'title'     => (string) get_the_title( (int) $row['event_id'] ),
					'permalink' => (string) get_permalink( (int) $row['event_id'] ),
					'start'     => Event_Format::occurrence_start( $row, $date_fmt, $time_fmt ),
				];
			}

			$venues[] = [
				'id'         => $venue_id,
				'title'      => (string) $post->post_title,
				'permalink'  => (string) get_permalink( $venue_id ),
				'content'    => post_password_required( $venue_id ) ? '' : (string) $post->post_content,
				'address'    => (string) get_post_meta( $venue_id, '_reevents_address', true ),
				'website'    => (string) get_post_meta( $venue_id, '_reevents_website', true ),
				'map_link'   => Field::map_link(
					(string) get_post_meta( $venue_id, '_reevents_lat', true ),
					(string) get_post_meta( $venue_id, '_reevents_lng', true )
				),
				'next_event' => $next_event,
			];
		}

		$base_url = (string) remove_query_arg( [ $param, 'reevents_venue_page' ] );
		$base_url = '' !== $base_url ? $base_url : home_url( '/' );
		$pager    = $paginate ? Pagination::links( $param, $base_url, $page, (int) $query->max_num_pages, __( 'Venue pages', 're-events' ) ) : '';

		return Template::render(
			'venues-list',
			[
				'venues'     => $venues,
				'args'       => $args,
				'pagination' => $pager,
			]
		);
	}
}

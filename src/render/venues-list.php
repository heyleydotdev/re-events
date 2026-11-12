<?php
/**
 * Venues list renderer.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Render;

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
		$query    = new WP_Query(
			[
				'post_type'      => 'reevents_venue',
				'post_status'    => 'publish',
				'posts_per_page' => max( 1, (int) ( $args['limit'] ?? 10 ) ),
				'paged'          => $page,
				'no_found_rows'  => ! $paginate,
				'orderby'        => 'title',
				'order'          => 'ASC',
			]
		);

		$venues = [];

		foreach ( $query->posts as $post ) {
			$venues[] = [
				'id'        => (int) $post->ID,
				'title'     => (string) $post->post_title,
				'permalink' => (string) get_permalink( $post->ID ),
				'content'   => post_password_required( $post->ID ) ? '' : (string) $post->post_content,
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

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
		$query = new WP_Query(
			[
				'post_type'      => 'reevents_venue',
				'post_status'    => 'publish',
				'posts_per_page' => max( 1, (int) ( $args['limit'] ?? 10 ) ),
				'no_found_rows'  => true,
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

		return Template::render(
			'venues-list',
			[
				'venues' => $venues,
				'args'   => $args,
			]
		);
	}
}

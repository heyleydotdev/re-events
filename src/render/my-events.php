<?php
/**
 * My events renderer.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Render;

/**
 * Renders the current user's events.
 */
final class My_Events {

	/**
	 * Render the list.
	 *
	 * @param array<string, mixed> $args Widget arguments.
	 */
	public static function render( array $args ): string {
		if ( ! is_user_logged_in() ) {
			return '';
		}

		$limit  = max( 1, min( 100, (int) ( $args['limit'] ?? 20 ) ) );
		$posts  = get_posts(
			[
				'post_type'      => 'reevents_event',
				'post_status'    => [ 'publish', 'pending', 'draft' ],
				'author'         => get_current_user_id(),
				'posts_per_page' => $limit,
				'orderby'        => 'date',
				'order'          => 'DESC',
			]
		);
		$events = [];

		foreach ( $posts as $post ) {
			$events[] = [
				'id'      => (int) $post->ID,
				'title'   => (string) $post->post_title,
				'status'  => (string) $post->post_status,
				'url'     => (string) get_permalink( $post->ID ),
				'edit'    => add_query_arg( 'reevents_edit', $post->ID, self::current_url() ),
				'created' => (string) $post->post_date,
			];
		}

		return Template::render(
			'my-events',
			[
				'events' => $events,
				'args'   => $args,
			]
		);
	}

	/**
	 * Current page URL.
	 */
	private static function current_url(): string {
		$url = get_permalink();

		return is_string( $url ) && '' !== $url ? $url : home_url( '/' );
	}
}

<?php
/**
 * Organizer repository.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Data;

use WP_Error;
use WP_Post;
use WP_Query;

/**
 * Reads and writes organizers.
 */
final class Organizer_Repository {

	/**
	 * Find an organizer.
	 *
	 * @param int $id Post ID.
	 * @return array<string, mixed>|null
	 */
	public function find( int $id ): ?array {
		$post = get_post( $id );

		if ( ! $post instanceof WP_Post || 'reevents_organizer' !== $post->post_type ) {
			return null;
		}

		return $this->hydrate( $post );
	}

	/**
	 * Query organizers.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 * @return array{items:array<int, array<string, mixed>>,total:int,pages:int}
	 */
	public function query( array $args = [] ): array {
		$query = new WP_Query(
			[
				'post_type'      => 'reevents_organizer',
				'post_status'    => 'publish',
				'posts_per_page' => min( 100, max( 1, (int) ( $args['per_page'] ?? 10 ) ) ),
				'paged'          => max( 1, (int) ( $args['page'] ?? 1 ) ),
				's'              => isset( $args['search'] ) ? sanitize_text_field( (string) $args['search'] ) : '',
				'orderby'        => 'title',
				'order'          => 'ASC',
			]
		);

		$items = [];

		foreach ( $query->posts as $post ) {
			$items[] = $this->hydrate( $post );
		}

		return [
			'items' => $items,
			'total' => (int) $query->found_posts,
			'pages' => (int) $query->max_num_pages,
		];
	}

	/**
	 * Save an organizer.
	 *
	 * @param array<string, mixed> $organizer Organizer data.
	 * @return int|WP_Error
	 */
	public function save( array $organizer ) {
		$id     = (int) ( $organizer['id'] ?? 0 );
		$status = 'publish';

		if ( $id ) {
			$existing_status = get_post_status( $id );

			if ( $existing_status ) {
				$status = $existing_status;
			}
		}

		$postarr = [
			'ID'           => $id,
			'post_type'    => 'reevents_organizer',
			'post_title'   => (string) ( $organizer['title'] ?? '' ),
			'post_content' => (string) ( $organizer['description'] ?? '' ),
			'post_status'  => $status,
		];

		return $id ? wp_update_post( $postarr, true ) : wp_insert_post( $postarr, true );
	}

	/**
	 * Delete an organizer.
	 *
	 * @param int $id Post ID.
	 */
	public function delete( int $id ): bool {
		if ( 'reevents_organizer' !== get_post_type( $id ) ) {
			return false;
		}

		return wp_delete_post( $id, true ) instanceof WP_Post;
	}

	/**
	 * Hydrate an organizer.
	 *
	 * @param WP_Post $post Post object.
	 * @return array<string, mixed>
	 */
	private function hydrate( WP_Post $post ): array {
		return [
			'id'          => $post->ID,
			'title'       => $post->post_title,
			'description' => $post->post_content,
			'permalink'   => (string) get_permalink( $post->ID ),
		];
	}
}

<?php
/**
 * Venue repository.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Data;

use WP_Error;
use WP_Post;
use WP_Query;

/**
 * Reads and writes venues.
 */
final class Venue_Repository {

	/**
	 * Find a venue.
	 *
	 * @param int $id Post ID.
	 * @return array<string, mixed>|null
	 */
	public function find( int $id ): ?array {
		$post = get_post( $id );

		if ( ! $post instanceof WP_Post || 'reevents_venue' !== $post->post_type ) {
			return null;
		}

		return $this->hydrate( $post );
	}

	/**
	 * Query venues.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 * @return array{items:array<int, array<string, mixed>>,total:int,pages:int}
	 */
	public function query( array $args = [] ): array {
		$query = new WP_Query(
			[
				'post_type'      => 'reevents_venue',
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
	 * Save a venue.
	 *
	 * @param array<string, mixed> $venue Venue data.
	 * @return int|WP_Error
	 */
	public function save( array $venue ) {
		$id     = (int) ( $venue['id'] ?? 0 );
		$status = 'publish';

		if ( $id ) {
			$existing_status = get_post_status( $id );

			if ( $existing_status ) {
				$status = $existing_status;
			}
		}

		$postarr = [
			'ID'           => $id,
			'post_type'    => 'reevents_venue',
			'post_title'   => (string) ( $venue['title'] ?? '' ),
			'post_content' => (string) ( $venue['description'] ?? '' ),
			'post_status'  => $status,
			'meta_input'   => [
				'_reevents_address' => (string) ( $venue['address'] ?? '' ),
				'_reevents_lat'     => (string) ( $venue['lat'] ?? '' ),
				'_reevents_lng'     => (string) ( $venue['lng'] ?? '' ),
				'_reevents_website' => (string) ( $venue['website'] ?? '' ),
			],
		];

		return $id ? wp_update_post( $postarr, true ) : wp_insert_post( $postarr, true );
	}

	/**
	 * Delete a venue.
	 *
	 * @param int $id Post ID.
	 */
	public function delete( int $id ): bool {
		if ( 'reevents_venue' !== get_post_type( $id ) ) {
			return false;
		}

		return wp_delete_post( $id, true ) instanceof WP_Post;
	}

	/**
	 * Hydrate a venue.
	 *
	 * @param WP_Post $post Post object.
	 * @return array<string, mixed>
	 */
	private function hydrate( WP_Post $post ): array {
		return [
			'id'          => $post->ID,
			'title'       => $post->post_title,
			'description' => $post->post_content,
			'address'     => (string) get_post_meta( $post->ID, '_reevents_address', true ),
			'lat'         => (string) get_post_meta( $post->ID, '_reevents_lat', true ),
			'lng'         => (string) get_post_meta( $post->ID, '_reevents_lng', true ),
			'website'     => (string) get_post_meta( $post->ID, '_reevents_website', true ),
			'permalink'   => (string) get_permalink( $post->ID ),
		];
	}
}

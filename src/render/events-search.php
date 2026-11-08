<?php
/**
 * Events search renderer.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Render;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Renders the events search form and results.
 */
final class Events_Search {

	/**
	 * Render the search form.
	 *
	 * @param array<string, mixed> $args Widget arguments.
	 */
	public static function render( array $args ): string {
		$filters    = self::filters();
		$categories = self::categories();
		$venues     = self::venues();
		$results    = '';

		if ( self::has_filters( $filters ) && ! empty( $args['show_results'] ) ) {
			$results = Events_List::render(
				[
					'limit'      => max( 1, (int) ( $args['limit'] ?? 20 ) ),
					'category'   => $filters['category'],
					'venue'      => $filters['venue'],
					'upcoming'   => $filters['upcoming'],
					'search'     => $filters['search'],
					'from'       => $filters['from'],
					'to'         => $filters['to'],
					'show_venue' => ! empty( $args['show_venue'] ),
				]
			);
		}

		$action = get_permalink();

		if ( ! $action ) {
			$action = get_post_type_archive_link( 'reevents_event' );
		}

		if ( ! $action ) {
			$action = home_url( '/' );
		}

		return Template::render(
			'events-search',
			[
				'args'       => $args,
				'filters'    => $filters,
				'categories' => $categories,
				'venues'     => $venues,
				'results'    => $results,
				'action'     => $action,
			]
		);
	}

	/**
	 * Read and sanitize the search filters.
	 *
	 * @return array{search:string,category:int,venue:int,from:string,to:string,upcoming:bool}
	 */
	private static function filters(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters.
		$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$category = isset( $_GET['reevents_category'] ) ? absint( wp_unslash( $_GET['reevents_category'] ) ) : 0;
		$venue    = isset( $_GET['reevents_venue'] ) ? absint( wp_unslash( $_GET['reevents_venue'] ) ) : 0;
		$from     = self::date( isset( $_GET['reevents_from'] ) ? sanitize_text_field( wp_unslash( $_GET['reevents_from'] ) ) : '' );
		$to       = self::date( isset( $_GET['reevents_to'] ) ? sanitize_text_field( wp_unslash( $_GET['reevents_to'] ) ) : '' );
		$upcoming = ! empty( $_GET['reevents_upcoming'] );
		// phpcs:enable

		return [
			'search'   => $search,
			'category' => $category,
			'venue'    => $venue,
			'from'     => $from,
			'to'       => $to,
			'upcoming' => $upcoming,
		];
	}

	/**
	 * Whether any filter is active.
	 *
	 * @param array{search:string,category:int,venue:int,from:string,to:string,upcoming:bool} $filters Filters.
	 */
	private static function has_filters( array $filters ): bool {
		return '' !== $filters['search']
			|| $filters['category']
			|| $filters['venue']
			|| '' !== $filters['from']
			|| '' !== $filters['to']
			|| $filters['upcoming'];
	}

	/**
	 * Get categories for the form.
	 *
	 * @return array<int, array{id:int,name:string}>
	 */
	private static function categories(): array {
		$terms = get_terms(
			[
				'taxonomy'   => 'reevents_event_category',
				'hide_empty' => false,
			]
		);

		if ( is_wp_error( $terms ) ) {
			return [];
		}

		$categories = [];

		foreach ( $terms as $term ) {
			$categories[] = [
				'id'   => (int) $term->term_id,
				'name' => (string) $term->name,
			];
		}

		return $categories;
	}

	/**
	 * Get venues for the form.
	 *
	 * @return array<int, array{id:int,title:string}>
	 */
	private static function venues(): array {
		$posts  = get_posts(
			[
				'post_type'      => 'reevents_venue',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			]
		);
		$venues = [];

		foreach ( $posts as $post ) {
			$venues[] = [
				'id'    => (int) $post->ID,
				'title' => (string) $post->post_title,
			];
		}

		return $venues;
	}

	/**
	 * Validate a Y-m-d date.
	 *
	 * @param string $value Raw date.
	 */
	private static function date( string $value ): string {
		$value = trim( $value );

		if ( '' === $value ) {
			return '';
		}

		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value, new DateTimeZone( 'UTC' ) );

		return $date instanceof DateTimeImmutable ? $date->format( 'Y-m-d' ) : '';
	}
}

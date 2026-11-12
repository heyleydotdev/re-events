<?php
/**
 * Events search renderer.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Render;

use ReEvents\Data\Meta;

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
		$categories = ! empty( $args['show_category'] ) ? self::categories() : [];
		$venues     = ! empty( $args['show_venue'] ) ? self::venues() : [];
		$tags       = ! empty( $args['show_tag'] ) ? self::tags() : [];
		$organizers = ! empty( $args['show_organizer'] ) ? self::organizers() : [];
		$results    = '';

		if ( self::has_filters( $filters ) && ! empty( $args['show_results'] ) ) {
			$results = Events_List::render(
				array_merge(
					[
						'limit'          => max( 1, (int) ( $args['limit'] ?? 20 ) ),
						'category'       => $filters['category'],
						'tag'            => $filters['tag'],
						'venue'          => $filters['venue'],
						'organizer'      => $filters['organizer'],
						'upcoming'       => $filters['upcoming'],
						'search'         => $filters['search'],
						'from'           => $filters['from'],
						'to'             => $filters['to'],
						'order'          => $filters['order'],
						'show_venue'     => ! empty( $args['show_venue'] ),
						'paginate'       => ! empty( $args['paginate'] ),
						'pagination_key' => (string) ( $args['pagination_key'] ?? '' ),
					],
					Events_List::card_args( $args )
				)
			);
		}

		$action = is_post_type_archive( 'reevents_event' ) ? get_post_type_archive_link( 'reevents_event' ) : get_permalink();

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
				'tags'       => $tags,
				'organizers' => $organizers,
				'results'    => $results,
				'action'     => $action,
			]
		);
	}

	/**
	 * Read and sanitize the search filters.
	 *
	 * @return array{search:string,category:int,tag:string,venue:int,organizer:int,from:string,to:string,order:string,upcoming:bool}
	 */
	private static function filters(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters.
		$search    = isset( $_GET['reevents_search'] ) ? sanitize_text_field( wp_unslash( $_GET['reevents_search'] ) ) : '';
		$category  = isset( $_GET['reevents_category'] ) ? absint( wp_unslash( $_GET['reevents_category'] ) ) : 0;
		$tag       = isset( $_GET['reevents_tag'] ) ? sanitize_text_field( wp_unslash( $_GET['reevents_tag'] ) ) : '';
		$venue     = isset( $_GET['reevents_venue_filter'] ) ? absint( wp_unslash( $_GET['reevents_venue_filter'] ) ) : 0;
		$organizer = isset( $_GET['reevents_organizer'] ) ? absint( wp_unslash( $_GET['reevents_organizer'] ) ) : 0;
		$from      = Meta::sanitize_date( isset( $_GET['reevents_from'] ) ? sanitize_text_field( wp_unslash( $_GET['reevents_from'] ) ) : '' );
		$to        = Meta::sanitize_date( isset( $_GET['reevents_to'] ) ? sanitize_text_field( wp_unslash( $_GET['reevents_to'] ) ) : '' );
		$order     = isset( $_GET['reevents_order'] ) && 'desc' === strtolower( sanitize_text_field( wp_unslash( $_GET['reevents_order'] ) ) ) ? 'desc' : 'asc';
		$upcoming  = ! empty( $_GET['reevents_upcoming'] );
		// phpcs:enable

		return [
			'search'    => $search,
			'category'  => $category,
			'tag'       => $tag,
			'venue'     => $venue,
			'organizer' => $organizer,
			'from'      => $from,
			'to'        => $to,
			'order'     => $order,
			'upcoming'  => $upcoming,
		];
	}

	/**
	 * Whether any filter is active.
	 *
	 * @param array{search:string,category:int,tag:string,venue:int,organizer:int,from:string,to:string,order:string,upcoming:bool} $filters Filters.
	 */
	private static function has_filters( array $filters ): bool {
		return '' !== $filters['search']
			|| $filters['category']
			|| '' !== $filters['tag']
			|| $filters['venue']
			|| $filters['organizer']
			|| '' !== $filters['from']
			|| '' !== $filters['to']
			|| $filters['upcoming'];
	}

	/**
	 * Get tags for the form.
	 *
	 * @return array<int, array{id:int,name:string}>
	 */
	private static function tags(): array {
		$terms = get_terms(
			[
				'taxonomy'   => 'reevents_event_tag',
				'hide_empty' => false,
			]
		);

		if ( is_wp_error( $terms ) ) {
			return [];
		}

		$tags = [];

		foreach ( $terms as $term ) {
			$tags[] = [
				'id'   => (int) $term->term_id,
				'name' => (string) $term->name,
			];
		}

		return $tags;
	}

	/**
	 * Get organizers for the form.
	 *
	 * @return array<int, array{id:int,title:string}>
	 */
	private static function organizers(): array {
		$limit = max( 1, (int) apply_filters( 'reevents_organizer_dropdown_limit', 500 ) );
		$posts = get_posts(
			[
				'post_type'      => 'reevents_organizer',
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'orderby'        => 'title',
				'order'          => 'ASC',
			]
		);
		$items = [];

		foreach ( $posts as $post ) {
			$items[] = [
				'id'    => (int) $post->ID,
				'title' => (string) $post->post_title,
			];
		}

		return $items;
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
		$limit  = max( 1, (int) apply_filters( 'reevents_venue_dropdown_limit', 500 ) );
		$posts  = get_posts(
			[
				'post_type'      => 'reevents_venue',
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
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
}

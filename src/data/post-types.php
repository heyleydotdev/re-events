<?php
/**
 * Post types.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Data;

/**
 * Registers the plugin post types.
 */
final class Post_Types {

	/**
	 * Hook into WordPress.
	 */
	public static function register(): void {
		add_action( 'init', [ self::class, 'register_post_types' ] );
	}

	/**
	 * Register post types.
	 */
	public static function register_post_types(): void {
		register_post_type(
			'reevents_event',
			[
				'labels'          => self::labels( __( 'Event', 're-events' ), __( 'Events', 're-events' ) ),
				'public'          => true,
				'show_in_rest'    => true,
				'rest_base'       => 'reevents-events',
				'has_archive'     => 'events',
				'rewrite'         => [
					'slug'       => 'events',
					'with_front' => false,
				],
				'show_in_menu'    => 're-events',
				'supports'        => [ 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'revisions' ],
				'capability_type' => 'reevents_event',
				'map_meta_cap'    => true,
				'taxonomies'      => [ 'reevents_event_category', 'reevents_event_tag' ],
			]
		);

		register_post_type(
			'reevents_venue',
			[
				'labels'          => self::labels( __( 'Venue', 're-events' ), __( 'Venues', 're-events' ) ),
				'public'          => true,
				'show_in_rest'    => true,
				'rest_base'       => 'reevents-venues',
				'has_archive'     => 'venues',
				'rewrite'         => [
					'slug'       => 'venues',
					'with_front' => false,
				],
				'show_in_menu'    => 're-events',
				'supports'        => [ 'title', 'editor', 'thumbnail', 'revisions' ],
				'capability_type' => 'reevents_venue',
				'map_meta_cap'    => true,
			]
		);

		register_post_type(
			'reevents_organizer',
			[
				'labels'          => self::labels( __( 'Organizer', 're-events' ), __( 'Organizers', 're-events' ) ),
				'public'          => true,
				'show_in_rest'    => true,
				'rest_base'       => 'reevents-organizers',
				'has_archive'     => 'organizers',
				'rewrite'         => [
					'slug'       => 'organizers',
					'with_front' => false,
				],
				'show_in_menu'    => 're-events',
				'supports'        => [ 'title', 'editor', 'thumbnail', 'revisions' ],
				'capability_type' => 'reevents_organizer',
				'map_meta_cap'    => true,
			]
		);
	}

	/**
	 * Build post type labels.
	 *
	 * @param string $singular Singular label.
	 * @param string $plural   Plural label.
	 * @return array<string, string>
	 */
	private static function labels( string $singular, string $plural ): array {
		return [
			'name'          => $plural,
			'singular_name' => $singular,
			'menu_name'     => $plural,
			'all_items'     => $plural,
			'add_new_item'  => sprintf(
				/* translators: %s: post type singular name. */
				__( 'Add New %s', 're-events' ),
				$singular
			),
			'edit_item'     => sprintf(
				/* translators: %s: post type singular name. */
				__( 'Edit %s', 're-events' ),
				$singular
			),
			'new_item'      => sprintf(
				/* translators: %s: post type singular name. */
				__( 'New %s', 're-events' ),
				$singular
			),
			'view_item'     => sprintf(
				/* translators: %s: post type singular name. */
				__( 'View %s', 're-events' ),
				$singular
			),
			'search_items'  => sprintf(
				/* translators: %s: plural name. */
				__( 'Search %s', 're-events' ),
				$plural
			),
			'not_found'     => sprintf(
				/* translators: %s: plural name. */
				__( 'No %s found.', 're-events' ),
				strtolower( $plural )
			),
		];
	}
}

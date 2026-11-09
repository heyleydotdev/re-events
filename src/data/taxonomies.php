<?php
/**
 * Taxonomies.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Data;

/**
 * Registers the plugin taxonomies.
 */
final class Taxonomies {

	/**
	 * Hook into WordPress.
	 */
	public static function register(): void {
		add_action( 'init', [ self::class, 'register_taxonomies' ] );
	}

	/**
	 * Register taxonomies.
	 */
	public static function register_taxonomies(): void {
		register_taxonomy(
			'reevents_event_category',
			[ 'reevents_event' ],
			[
				'labels'            => self::labels( __( 'Event Category', 're-events' ), __( 'Event Categories', 're-events' ) ),
				'hierarchical'      => true,
				'public'            => true,
				'show_in_rest'      => true,
				'rest_base'         => 'reevents-event-categories',
				'rewrite'           => [
					'slug'       => 'event-category',
					'with_front' => false,
				],
				'show_admin_column' => true,
			]
		);

		register_taxonomy(
			'reevents_event_tag',
			[ 'reevents_event' ],
			[
				'labels'            => self::labels( __( 'Event Tag', 're-events' ), __( 'Event Tags', 're-events' ) ),
				'hierarchical'      => false,
				'public'            => true,
				'show_in_rest'      => true,
				'rest_base'         => 'reevents-event-tags',
				'rewrite'           => [
					'slug'       => 'event-tag',
					'with_front' => false,
				],
				'show_admin_column' => true,
			]
		);

		register_taxonomy(
			'reevents_organizer_type',
			[ 'reevents_organizer' ],
			[
				'labels'            => self::labels( __( 'Organizer Tag', 're-events' ), __( 'Organizer Tags', 're-events' ) ),
				'hierarchical'      => false,
				'public'            => false,
				'show_ui'           => true,
				'show_in_rest'      => true,
				'rest_base'         => 'reevents-organizer-types',
				'show_admin_column' => true,
				'capabilities'      => [
					'manage_terms' => 'edit_reevents_organizers',
					'edit_terms'   => 'edit_reevents_organizers',
					'delete_terms' => 'edit_reevents_organizers',
					'assign_terms' => 'edit_reevents_organizers',
				],
			]
		);
	}

	/**
	 * Build taxonomy labels.
	 *
	 * @param string $singular Singular label.
	 * @param string $plural   Plural label.
	 * @return array<string, string>
	 */
	private static function labels( string $singular, string $plural ): array {
		return [
			'name'              => $plural,
			'singular_name'     => $singular,
			'menu_name'         => $plural,
			'all_items'         => $plural,
			'edit_item'         => sprintf(
				/* translators: %s: taxonomy singular name. */
				__( 'Edit %s', 're-events' ),
				$singular
			),
			'view_item'         => sprintf(
				/* translators: %s: taxonomy singular name. */
				__( 'View %s', 're-events' ),
				$singular
			),
			'update_item'       => sprintf(
				/* translators: %s: taxonomy singular name. */
				__( 'Update %s', 're-events' ),
				$singular
			),
			'add_new_item'      => sprintf(
				/* translators: %s: taxonomy singular name. */
				__( 'Add New %s', 're-events' ),
				$singular
			),
			'new_item_name'     => sprintf(
				/* translators: %s: taxonomy singular name. */
				__( 'New %s Name', 're-events' ),
				$singular
			),
			'parent_item'       => sprintf(
				/* translators: %s: taxonomy singular name. */
				__( 'Parent %s', 're-events' ),
				$singular
			),
			'parent_item_colon' => sprintf(
				/* translators: %s: taxonomy singular name. */
				__( 'Parent %s:', 're-events' ),
				$singular
			),
			'search_items'      => sprintf(
				/* translators: %s: taxonomy plural name. */
				__( 'Search %s', 're-events' ),
				$plural
			),
			'not_found'         => sprintf(
				/* translators: %s: taxonomy plural name. */
				__( 'No %s found.', 're-events' ),
				strtolower( $plural )
			),
		];
	}
}

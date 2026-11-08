<?php
/**
 * Admin menu.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Admin;

use WP_Screen;

/**
 * Registers the ReEvents admin menu.
 */
final class Menu {

	/**
	 * Hook into WordPress.
	 */
	public static function register(): void {
		add_action( 'admin_menu', [ self::class, 'add' ] );
		add_filter( 'parent_file', [ self::class, 'fix_taxonomy_parent' ] );
		add_filter( 'submenu_file', [ self::class, 'fix_taxonomy_submenu' ] );
	}

	/**
	 * Add the top-level menu.
	 */
	public static function add(): void {
		add_menu_page(
			__( 'ReEvents', 're-events' ),
			__( 'ReEvents', 're-events' ),
			'manage_options',
			're-events',
			[ self::class, 'render' ],
			'dashicons-calendar-alt',
			30
		);

		self::add_taxonomy_submenus();
	}

	/**
	 * Add taxonomy screens under the ReEvents menu.
	 *
	 * Core only adds taxonomy submenus to top-level post type menus, so
	 * post types nested under a custom menu need this.
	 */
	private static function add_taxonomy_submenus(): void {
		foreach ( [ 'reevents_event_category', 'reevents_event_tag' ] as $taxonomy_name ) {
			$taxonomy = get_taxonomy( $taxonomy_name );

			if ( ! $taxonomy ) {
				continue;
			}

			add_submenu_page(
				're-events',
				$taxonomy->labels->name,
				$taxonomy->labels->menu_name,
				$taxonomy->cap->manage_terms,
				'edit-tags.php?taxonomy=' . $taxonomy->name . '&post_type=reevents_event'
			);
		}
	}

	/**
	 * Keep the ReEvents menu highlighted on taxonomy screens.
	 *
	 * @param string $parent_file Current parent file.
	 */
	public static function fix_taxonomy_parent( string $parent_file ): string {
		return self::event_taxonomy_screen() ? 're-events' : $parent_file;
	}

	/**
	 * Highlight the current taxonomy submenu item.
	 *
	 * @param string $submenu_file Current submenu file.
	 */
	public static function fix_taxonomy_submenu( string $submenu_file ): string {
		$screen = self::event_taxonomy_screen();

		if ( ! $screen ) {
			return $submenu_file;
		}

		return 'edit-tags.php?taxonomy=' . $screen->taxonomy . '&post_type=reevents_event';
	}

	/**
	 * Get the current screen when it is an event taxonomy screen.
	 */
	private static function event_taxonomy_screen(): ?WP_Screen {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return null;
		}

		if ( 'reevents_event' !== $screen->post_type ) {
			return null;
		}

		if ( ! in_array( $screen->taxonomy, [ 'reevents_event_category', 'reevents_event_tag' ], true ) ) {
			return null;
		}

		return $screen;
	}

	/**
	 * Render the admin page.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="wrap"><h1>' . esc_html__( 'ReEvents', 're-events' ) . '</h1></div>';
	}
}

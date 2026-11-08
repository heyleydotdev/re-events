<?php
/**
 * Admin menu.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Admin;

/**
 * Registers the ReEvents admin menu.
 */
final class Menu {

	/**
	 * Hook into WordPress.
	 */
	public static function register(): void {
		add_action( 'admin_menu', [ self::class, 'add' ] );
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

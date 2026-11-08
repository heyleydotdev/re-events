<?php
/**
 * Admin assets.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Admin;

/**
 * Enqueues admin assets.
 */
final class Assets {

	/**
	 * Hook into WordPress.
	 */
	public static function register(): void {
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue' ] );
	}

	/**
	 * Enqueue event editor assets.
	 *
	 * @param string $hook Current admin page.
	 */
	public static function enqueue( string $hook ): void {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen || 'reevents_event' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_script(
			'reevents-admin',
			plugins_url( 'assets/js/admin.js', RE_EVENTS_FILE ),
			[],
			RE_EVENTS_VERSION,
			true
		);

		wp_enqueue_style(
			'reevents-admin',
			plugins_url( 'assets/css/admin.css', RE_EVENTS_FILE ),
			[],
			RE_EVENTS_VERSION
		);
	}
}

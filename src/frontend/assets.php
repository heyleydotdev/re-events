<?php
/**
 * Frontend assets.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Frontend;

/**
 * Enqueues frontend assets.
 */
final class Assets {

	/**
	 * Hook into WordPress.
	 */
	public static function register(): void {
		add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue' ] );
	}

	/**
	 * Enqueue frontend styles.
	 */
	public static function enqueue(): void {
		wp_enqueue_script(
			'reevents-frontend',
			plugins_url( 'assets/js/frontend.js', RE_EVENTS_FILE ),
			[],
			RE_EVENTS_VERSION,
			true
		);

		wp_enqueue_style(
			'reevents-frontend',
			plugins_url( 'assets/css/frontend.css', RE_EVENTS_FILE ),
			[],
			RE_EVENTS_VERSION
		);
	}
}

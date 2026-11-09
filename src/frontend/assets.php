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
		if ( ! self::needed() ) {
			return;
		}

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

	/**
	 * Whether the current request can render a ReEvents widget.
	 */
	private static function needed(): bool {
		if ( is_singular( [ 'reevents_event', 'reevents_venue' ] ) || is_post_type_archive( [ 'reevents_event', 'reevents_venue' ] ) ) {
			return true;
		}

		$post = get_post();

		if ( ! $post ) {
			return false;
		}

		foreach ( [ 'reevents_list', 'reevents_event', 'reevents_venues', 'reevents_search', 'reevents_calendar', 'reevents_map', 'reevents_booking' ] as $shortcode ) {
			if ( has_shortcode( $post->post_content, $shortcode ) ) {
				return true;
			}
		}

		foreach ( [ 'reevents/events-list', 'reevents/event-details', 'reevents/venues-list', 'reevents/events-search', 'reevents/calendar', 'reevents/map' ] as $block ) {
			if ( has_block( $block, $post ) ) {
				return true;
			}
		}

		return false;
	}
}

<?php
/**
 * Event filter for booking tables.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Admin;

/**
 * Enqueues the searchable event filter.
 */
final class Event_Filter {

	/**
	 * Hook into WordPress.
	 */
	public static function register(): void {
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue' ] );
	}

	/**
	 * Enqueue assets on the booking screens.
	 *
	 * @param string $hook Current admin page.
	 */
	public static function enqueue( string $hook ): void {
		if ( ! in_array( $hook, [ 'reevents_page_reevents-bookings', 'reevents_page_reevents-attendees' ], true ) ) {
			return;
		}

		wp_enqueue_script(
			'reevents-event-filter',
			plugins_url( 'assets/js/admin-event-filter.js', RE_EVENTS_FILE ),
			[ 'wp-api-fetch' ],
			RE_EVENTS_VERSION,
			true
		);

		wp_enqueue_style(
			'reevents-event-filter',
			plugins_url( 'assets/css/admin-event-filter.css', RE_EVENTS_FILE ),
			[],
			RE_EVENTS_VERSION
		);
	}
}

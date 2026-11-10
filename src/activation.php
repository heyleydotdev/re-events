<?php
/**
 * Activation and deactivation.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents;

use ReEvents\Data\Schema;

/**
 * Runs on plugin activation and version changes.
 */
final class Activation {

	/**
	 * Activate the plugin.
	 */
	public static function activate(): void {
		self::upgrade();
	}

	/**
	 * Deactivate the plugin.
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'reevents_expire_bookings' );
		wp_unschedule_hook( 'reevents_send_booking_emails' );
		flush_rewrite_rules();
	}

	/**
	 * Apply upgrades when the plugin version changes.
	 */
	public static function maybe_upgrade(): void {
		if ( version_compare( (string) get_option( 'reevents_version', '0' ), RE_EVENTS_VERSION, '>=' ) ) {
			return;
		}

		self::upgrade();
	}

	/**
	 * Grant capabilities and refresh rewrite rules.
	 */
	private static function upgrade(): void {
		Schema::install();
		Capabilities::add();
		flush_rewrite_rules();
		update_option( 'reevents_version', RE_EVENTS_VERSION );
	}
}

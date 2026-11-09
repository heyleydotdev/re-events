<?php
/**
 * Database schema.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Data;

/**
 * Creates and upgrades plugin tables.
 */
final class Schema {

	/**
	 * Schema version.
	 */
	public const VERSION = 1;

	/**
	 * Schema version option.
	 */
	private const OPTION = 'reevents_db_version';

	/**
	 * Install tables when the schema version changes.
	 */
	public static function maybe_install(): void {
		if ( (int) get_option( self::OPTION, 0 ) >= self::VERSION ) {
			return;
		}

		self::install();
	}

	/**
	 * Create or update tables.
	 */
	public static function install(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( self::sql() );

		if ( ! self::tables_exist() ) {
			return;
		}

		update_option( self::OPTION, self::VERSION );
	}

	/**
	 * Check that all plugin tables exist.
	 */
	private static function tables_exist(): bool {
		global $wpdb;

		foreach ( self::tables() as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Install-time schema check.
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );

			if ( $found !== $table ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Plugin table names.
	 *
	 * @return string[]
	 */
	private static function tables(): array {
		global $wpdb;

		return [
			$wpdb->prefix . 'reevents_occurrences',
		];
	}

	/**
	 * Build the table SQL.
	 */
	private static function sql(): string {
		global $wpdb;

		$charset = $wpdb->get_charset_collate();
		$prefix  = $wpdb->prefix;

		$occurrences = "CREATE TABLE {$prefix}reevents_occurrences (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_id bigint(20) unsigned NOT NULL,
			start_utc datetime NOT NULL,
			end_utc datetime NOT NULL,
			timezone varchar(64) NOT NULL DEFAULT 'UTC',
			timeslot_key varchar(64) NOT NULL DEFAULT '',
			capacity int(11) unsigned NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'scheduled',
			PRIMARY KEY  (id),
			KEY event_start (event_id,start_utc),
			KEY start_end (start_utc,end_utc)
		) {$charset};";

		return $occurrences;
	}
}

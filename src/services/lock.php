<?php
/**
 * Short-lived cross-request locks.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Services;

/**
 * Uses options as atomic locks between PHP requests.
 */
final class Lock {

	/**
	 * Try to take a lock.
	 *
	 * @param string $key Lock option name.
	 * @param int    $ttl Seconds before a stale lock is ignored.
	 */
	public static function acquire( string $key, int $ttl = 120 ): bool {
		if ( add_option( $key, time(), '', false ) ) {
			return true;
		}

		$created = self::stamp( $key );

		if ( $created > 0 && time() - $created > $ttl ) {
			delete_option( $key );

			return (bool) add_option( $key, time(), '', false );
		}

		return false;
	}

	/**
	 * Read the lock timestamp directly from the database.
	 *
	 * @param string $key Lock option name.
	 */
	private static function stamp( string $key ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Lock storage.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table name.
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
				$key
			)
		);
	}

	/**
	 * Release a lock.
	 *
	 * @param string $key Lock option name.
	 */
	public static function release( string $key ): void {
		delete_option( $key );
	}
}

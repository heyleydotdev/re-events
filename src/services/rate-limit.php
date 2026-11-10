<?php
/**
 * Rate limit helper.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Services;

/**
 * Limits repeated actions per IP using transients.
 */
final class Rate_Limit {

	/**
	 * Record an attempt.
	 *
	 * @param string $bucket Bucket name.
	 * @param int    $max    Maximum attempts.
	 * @param int    $window Window in seconds.
	 * @return bool True when the limit is reached.
	 */
	public static function hit( string $bucket, int $max = 5, int $window = 600 ): bool {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Remote address.
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ip = (string) apply_filters( 'reevents_client_ip', $ip );

		if ( '' === $ip ) {
			return false;
		}

		$key        = 'reevents_rate_' . md5( $bucket . '|' . $ip );
		$expiry_key = $key . '_expires';
		$now        = time();
		$expires    = self::value( $expiry_key );

		if ( $expires > 0 && $expires < $now ) {
			delete_option( $key );
			delete_option( $expiry_key );
		}

		$count = self::value( $key );

		if ( $count >= $max ) {
			return true;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Atomic counter in the options table.
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table name.
				"INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %d, 'no') ON DUPLICATE KEY UPDATE option_value = option_value + 1",
				$key,
				1
			)
		);

		$count = self::value( $key );

		if ( 1 === $count || 0 === self::value( $expiry_key ) ) {
			update_option( $expiry_key, $now + $window, false );
		}

		return $count > $max;
	}

	/**
	 * Read an option value without the object cache.
	 *
	 * @param string $key Option name.
	 */
	private static function value( string $key ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Atomic counter storage.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table name.
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
				$key
			)
		);
	}
}

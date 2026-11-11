<?php
/**
 * Rate limit helper.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Services;

use ReEvents\Admin\Settings;

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

		if ( Settings::get( 'trust_proxy_ip', false ) ) {
			$ip = self::forwarded_ip( $ip );
		}

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

	/**
	 * Read the client address from a trusted proxy header.
	 *
	 * @param string $fallback Address to keep when no header is valid.
	 */
	private static function forwarded_ip( string $fallback ): string {
		$forwarded = '';

		if ( isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated below.
			$forwarded = sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
		} elseif ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated below.
			$parts     = explode( ',', sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
			$forwarded = trim( $parts[0] );
		}

		return '' !== $forwarded && false !== filter_var( $forwarded, FILTER_VALIDATE_IP ) ? $forwarded : $fallback;
	}

	/**
	 * Delete counters whose window has passed.
	 */
	public static function cleanup(): void {
		global $wpdb;

		$counters = $wpdb->esc_like( 'reevents_rate_' ) . '%';
		$expiry   = $wpdb->esc_like( 'reevents_rate_' ) . '%' . $wpdb->esc_like( '_expires' );
		$now      = time();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Cleanup query.
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table name.
				"DELETE o FROM {$wpdb->options} o INNER JOIN {$wpdb->options} e ON e.option_name = CONCAT(o.option_name, '_expires') WHERE o.option_name LIKE %s AND e.option_value < %d",
				$counters,
				$now
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Cleanup query.
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table name.
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
				$expiry,
				$now
			)
		);
	}
}

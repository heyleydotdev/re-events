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

		$key   = 'reevents_rate_' . md5( $bucket . '|' . $ip );
		$count = (int) get_transient( $key );

		if ( $count >= $max ) {
			return true;
		}

		set_transient( $key, $count + 1, $window );

		return false;
	}
}

<?php
/**
 * Gateway helpers.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Services\Gateways;

/**
 * Shared gateway helpers.
 */
final class Gateway_Utils {

	/**
	 * Read a header by normalized name.
	 *
	 * @param array<string, mixed> $headers Headers.
	 * @param string               $name    Normalized name.
	 */
	public static function header( array $headers, string $name ): string {
		foreach ( $headers as $key => $value ) {
			if ( strtolower( str_replace( '-', '_', (string) $key ) ) !== $name ) {
				continue;
			}

			return is_array( $value ) ? (string) reset( $value ) : (string) $value;
		}

		return '';
	}
}

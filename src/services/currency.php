<?php
/**
 * Currency helper.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Services;

/**
 * Converts between smallest currency units and display values.
 */
final class Currency {

	/**
	 * ISO 4217 currencies with no minor unit.
	 */
	private const ZERO_DECIMAL = [
		'BIF',
		'CLP',
		'DJF',
		'GNF',
		'ISK',
		'JPY',
		'KMF',
		'KRW',
		'PYG',
		'RWF',
		'UGX',
		'UYI',
		'VND',
		'VUV',
		'XAF',
		'XOF',
		'XPF',
	];

	/**
	 * Number of decimal places for a currency.
	 *
	 * @param string $currency Currency code.
	 */
	public static function decimals( string $currency ): int {
		return in_array( strtoupper( $currency ), self::ZERO_DECIMAL, true ) ? 0 : 2;
	}

	/**
	 * Convert a smallest-unit amount to a plain decimal string.
	 *
	 * @param int    $amount_minor Amount in the smallest unit.
	 * @param string $currency     Currency code.
	 */
	public static function amount( int $amount_minor, string $currency ): string {
		$decimals = self::decimals( $currency );

		return number_format( $amount_minor / ( 10 ** $decimals ), $decimals, '.', '' );
	}

	/**
	 * Format an amount for display.
	 *
	 * @param int    $amount_minor Amount in the smallest unit.
	 * @param string $currency     Currency code.
	 */
	public static function format( int $amount_minor, string $currency ): string {
		$decimals = self::decimals( $currency );

		return number_format_i18n( $amount_minor / ( 10 ** $decimals ), $decimals ) . ' ' . strtoupper( $currency );
	}
}

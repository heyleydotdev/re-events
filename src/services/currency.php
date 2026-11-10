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
	 * ISO 4217 currencies with three minor units.
	 */
	private const THREE_DECIMAL = [
		'BHD',
		'IQD',
		'JOD',
		'KWD',
		'LYD',
		'OMR',
		'TND',
	];

	/**
	 * Number of decimal places for a currency.
	 *
	 * @param string $currency Currency code.
	 */
	public static function decimals( string $currency ): int {
		$currency = strtoupper( $currency );

		if ( in_array( $currency, self::ZERO_DECIMAL, true ) ) {
			return 0;
		}

		if ( in_array( $currency, self::THREE_DECIMAL, true ) ) {
			return 3;
		}

		return 2;
	}

	/**
	 * Sanitize a currency code.
	 *
	 * @param string $currency Raw code.
	 */
	public static function sanitize_code( string $currency ): string {
		$currency = strtoupper( preg_replace( '/[^A-Za-z]/', '', $currency ) ?? '' );

		return 3 === strlen( $currency ) ? $currency : 'USD';
	}

	/**
	 * Convert a plain decimal string to the smallest unit.
	 *
	 * @param string $amount   Decimal amount.
	 * @param string $currency Currency code.
	 */
	public static function minor_units( string $amount, string $currency ): int {
		if ( ! is_numeric( $amount ) ) {
			return 0;
		}

		return (int) round( (float) $amount * ( 10 ** self::decimals( $currency ) ) );
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
	 * Convert an amount to Stripe's two-decimal minor unit.
	 *
	 * Stripe requires three-decimal currencies to end in zero.
	 *
	 * @param int    $amount_minor Amount in the smallest unit.
	 * @param string $currency     Currency code.
	 */
	public static function stripe_amount( int $amount_minor, string $currency ): int {
		if ( 3 !== self::decimals( $currency ) ) {
			return $amount_minor;
		}

		return (int) ( round( $amount_minor / 10 ) * 10 );
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

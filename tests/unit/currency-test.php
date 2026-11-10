<?php
/**
 * Currency tests.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReEvents\Services\Currency;

/**
 * Covers zero-decimal currency handling.
 */
final class CurrencyTest extends TestCase {

	/**
	 * Two-decimal currencies use two decimals.
	 */
	public function test_two_decimal_amount(): void {
		self::assertSame( 2, Currency::decimals( 'usd' ) );
		self::assertSame( '12.34', Currency::amount( 1234, 'USD' ) );
	}

	/**
	 * Zero-decimal currencies are not divided by 100.
	 */
	public function test_zero_decimal_amount(): void {
		self::assertSame( 0, Currency::decimals( 'JPY' ) );
		self::assertSame( '500', Currency::amount( 500, 'JPY' ) );
	}

	/**
	 * Three-decimal currencies keep three decimals and round for Stripe.
	 */
	public function test_three_decimal_amount(): void {
		self::assertSame( 3, Currency::decimals( 'KWD' ) );
		self::assertSame( '1.234', Currency::amount( 1234, 'KWD' ) );
		self::assertSame( 1230, Currency::stripe_amount( 1234, 'KWD' ) );
	}
}

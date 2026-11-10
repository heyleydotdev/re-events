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
}

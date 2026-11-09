<?php
/**
 * Recurrence rule tests.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReEvents\Domain\Recurrence_Rule;

/**
 * Covers the editor RRULE subset.
 */
final class RecurrenceRuleTest extends TestCase {

	/**
	 * Invalid frequencies produce no rule.
	 */
	public function test_build_rejects_invalid_frequency(): void {
		self::assertSame( '', Recurrence_Rule::build( [ 'freq' => 'none' ] ) );
		self::assertSame( '', Recurrence_Rule::build( [ 'freq' => 'hourly' ] ) );
	}

	/**
	 * Daily rules include intervals only when greater than one.
	 */
	public function test_build_daily(): void {
		self::assertSame( 'FREQ=DAILY', Recurrence_Rule::build( [ 'freq' => 'daily' ] ) );
		self::assertSame(
			'FREQ=DAILY;INTERVAL=3',
			Recurrence_Rule::build(
				[
					'freq'     => 'daily',
					'interval' => 3,
				]
			)
		);
	}

	/**
	 * Weekly rules keep known weekdays in the canonical order.
	 */
	public function test_build_weekly_weekdays(): void {
		$rule = Recurrence_Rule::build(
			[
				'freq'     => 'weekly',
				'weekdays' => [ 'fr', 'mo', 'XX', 'FR' ],
			]
		);

		self::assertSame( 'FREQ=WEEKLY;BYDAY=MO,FR', $rule );
	}

	/**
	 * Monthly days are clamped to a valid range.
	 */
	public function test_build_monthly_clamps_day(): void {
		self::assertSame(
			'FREQ=MONTHLY;BYMONTHDAY=31',
			Recurrence_Rule::build(
				[
					'freq'     => 'monthly',
					'monthday' => 99,
				]
			)
		);
		self::assertSame(
			'FREQ=MONTHLY;BYMONTHDAY=1',
			Recurrence_Rule::build(
				[
					'freq'     => 'monthly',
					'monthday' => -5,
				]
			)
		);
		self::assertSame(
			'FREQ=MONTHLY',
			Recurrence_Rule::build(
				[
					'freq'     => 'monthly',
					'monthday' => 0,
				]
			)
		);
	}

	/**
	 * Until dates are formatted as UTC end-of-day.
	 */
	public function test_build_until(): void {
		$rule = Recurrence_Rule::build(
			[
				'freq'  => 'daily',
				'end'   => 'until',
				'until' => '2026-12-31',
			]
		);

		self::assertSame( 'FREQ=DAILY;UNTIL=20261231T235959Z', $rule );
	}

	/**
	 * Counts are positive.
	 */
	public function test_build_count(): void {
		$rule = Recurrence_Rule::build(
			[
				'freq'  => 'daily',
				'end'   => 'count',
				'count' => 5,
			]
		);

		self::assertSame( 'FREQ=DAILY;COUNT=5', $rule );
	}

	/**
	 * Empty rules return defaults.
	 */
	public function test_parse_empty(): void {
		self::assertSame(
			[
				'freq'     => 'none',
				'interval' => 1,
				'weekdays' => [],
				'monthday' => 0,
				'end'      => 'never',
				'until'    => '',
				'count'    => 0,
			],
			Recurrence_Rule::parse( '' )
		);
	}

	/**
	 * Parsed rules round-trip through build.
	 */
	public function test_parse_round_trip(): void {
		$parsed = Recurrence_Rule::parse( 'FREQ=WEEKLY;INTERVAL=2;BYDAY=MO,WE;COUNT=4' );

		self::assertSame( 'weekly', $parsed['freq'] );
		self::assertSame( 2, $parsed['interval'] );
		self::assertSame( [ 'MO', 'WE' ], $parsed['weekdays'] );
		self::assertSame( 'count', $parsed['end'] );
		self::assertSame( 4, $parsed['count'] );
		self::assertSame( 'FREQ=WEEKLY;INTERVAL=2;BYDAY=MO,WE;COUNT=4', Recurrence_Rule::build( $parsed ) );
	}

	/**
	 * Parsed UNTIL values return a date.
	 */
	public function test_parse_until(): void {
		$parsed = Recurrence_Rule::parse( 'FREQ=DAILY;UNTIL=20261231T235959Z' );

		self::assertSame( 'until', $parsed['end'] );
		self::assertSame( '2026-12-31', $parsed['until'] );
	}
}

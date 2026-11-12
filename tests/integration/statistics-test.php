<?php
/**
 * Statistics tests.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Tests\Integration;

use ReEvents\Admin\Statistics;
use ReEvents\Data\Booking_Repository;
use ReEvents\Data\Payment_Repository;
use ReEvents\Data\Schema;
use ReEvents\Data\Statistics_Repository;
use WP_UnitTestCase;

/**
 * Covers the statistics aggregates and page.
 */
final class Statistics_Test extends WP_UnitTestCase {

	/**
	 * Administrator with the plugin capabilities.
	 *
	 * @var int
	 */
	private int $admin = 0;

	/**
	 * Create an administrator.
	 */
	public function set_up(): void {
		parent::set_up();

		\ReEvents\Capabilities::add();
		update_option( 'timezone_string', '' );
		$this->admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $this->admin );
	}

	/**
	 * Create a booking row.
	 *
	 * @param array<string, mixed> $args Overrides.
	 */
	private function booking( array $args = [] ): int {
		$result = ( new Booking_Repository() )->create(
			array_merge(
				[
					'event_id'    => 1,
					'quantity'    => 1,
					'status'      => 'confirmed',
					'total_minor' => 0,
					'currency'    => 'USD',
					'created_utc' => '2027-01-10 12:00:00',
				],
				$args
			)
		);

		self::assertIsInt( $result );

		return $result;
	}

	/**
	 * Confirmed bookings set the summary; other statuses do not.
	 */
	public function test_summary(): void {
		$paid = $this->booking(
			[
				'quantity'    => 2,
				'total_minor' => 10000,
			]
		);
		$this->booking(
			[
				'quantity'    => 1,
				'total_minor' => 0,
			]
		);
		$this->booking(
			[
				'quantity'    => 5,
				'status'      => 'pending',
				'total_minor' => 5000,
			]
		);
		$this->booking(
			[
				'quantity'    => 3,
				'status'      => 'cancelled',
				'total_minor' => 3000,
			]
		);
		$this->booking(
			[
				'quantity'    => 1,
				'status'      => 'rejected',
				'total_minor' => 1000,
			]
		);
		$this->booking(
			[
				'quantity'    => 1,
				'total_minor' => 5000,
				'currency'    => 'EUR',
			]
		);

		( new Payment_Repository() )->create(
			[
				'booking_id'     => $paid,
				'status'         => 'succeeded',
				'amount_minor'   => 10000,
				'refunded_minor' => 2500,
				'currency'       => 'USD',
			]
		);

		$summary = ( new Statistics_Repository() )->summary(
			[
				'currency' => 'USD',
				'from'     => '2027-01-01 00:00:00',
				'to'       => '2027-01-31 23:59:59',
			]
		);

		self::assertSame( 2, $summary['bookings'] );
		self::assertSame( 3, $summary['tickets'] );
		self::assertSame( 10000, $summary['gross'] );
		self::assertSame( 2500, $summary['refunds'] );
		self::assertSame( 7500, $summary['sales'] );
	}

	/**
	 * Date and currency filters narrow the summary.
	 */
	public function test_filters(): void {
		$this->booking();
		$this->booking( [ 'created_utc' => '2027-02-10 12:00:00' ] );
		$this->booking( [ 'currency' => 'EUR' ] );

		$repo    = new Statistics_Repository();
		$january = $repo->summary(
			[
				'currency' => 'USD',
				'from'     => '2027-01-01 00:00:00',
				'to'       => '2027-01-31 23:59:59',
			]
		);
		$euro    = $repo->summary(
			[
				'currency' => 'EUR',
				'from'     => '2027-01-01 00:00:00',
				'to'       => '2027-01-31 23:59:59',
			]
		);

		self::assertSame( 1, $january['bookings'] );
		self::assertSame( 1, $euro['bookings'] );
		self::assertSame( [ 'EUR', 'USD' ], $repo->currencies() );
	}

	/**
	 * Monthly rows use net sales.
	 */
	public function test_monthly(): void {
		$january = $this->booking(
			[
				'quantity'    => 1,
				'total_minor' => 5000,
			]
		);
		$this->booking(
			[
				'created_utc' => '2027-02-10 12:00:00',
				'total_minor' => 8000,
			]
		);

		( new Payment_Repository() )->create(
			[
				'booking_id'     => $january,
				'status'         => 'succeeded',
				'amount_minor'   => 5000,
				'refunded_minor' => 1000,
				'currency'       => 'USD',
			]
		);

		$monthly = ( new Statistics_Repository() )->monthly(
			[
				'currency' => 'USD',
				'from'     => '2027-01-01 00:00:00',
				'to'       => '2027-03-31 23:59:59',
			]
		);

		self::assertSame(
			[
				[
					'month' => '2027-01',
					'sales' => 4000,
				],
				[
					'month' => '2027-02',
					'sales' => 8000,
				],
				[
					'month' => '2027-03',
					'sales' => 0,
				],
			],
			$monthly
		);
	}

	/**
	 * Empty data returns zeros.
	 */
	public function test_empty(): void {
		$summary = ( new Statistics_Repository() )->summary( [ 'currency' => 'XTS' ] );

		self::assertSame(
			[
				'bookings' => 0,
				'tickets'  => 0,
				'gross'    => 0,
				'refunds'  => 0,
				'sales'    => 0,
			],
			$summary
		);
	}

	/**
	 * A data-only payment update also refreshes the refund column.
	 */
	public function test_payment_data_updates_refund_column(): void {
		$booking = $this->booking();
		$repo    = new Payment_Repository();
		$payment = $repo->create(
			[
				'booking_id'   => $booking,
				'status'       => 'succeeded',
				'amount_minor' => 5000,
				'currency'     => 'USD',
			]
		);
		self::assertIsInt( $payment );

		$repo->update( $payment, [ 'data' => [ 'refunded_minor' => 1500 ] ] );

		self::assertSame( 1500, $repo->find( $payment )['refunded_minor'] );
	}

	/**
	 * Monthly buckets use the site time zone, not UTC.
	 */
	public function test_monthly_uses_site_timezone(): void {
		update_option( 'timezone_string', 'Pacific/Auckland' );
		$this->booking(
			[
				'currency'    => 'NZD',
				'total_minor' => 5000,
				'created_utc' => '2027-01-31 12:00:00',
			]
		);

		$monthly = ( new Statistics_Repository() )->monthly(
			[
				'currency' => 'NZD',
				'from'     => '2027-01-31 11:00:00',
				'to'       => '2027-02-28 10:59:59',
			]
		);

		self::assertSame(
			[
				[
					'month' => '2027-02',
					'sales' => 5000,
				],
			],
			$monthly
		);

		update_option( 'timezone_string', '' );
	}

	/**
	 * Old JSON refunds are copied into the new column.
	 */
	public function test_refund_backfill(): void {
		$booking = $this->booking();
		$payment = ( new Payment_Repository() )->create(
			[
				'booking_id'   => $booking,
				'status'       => 'succeeded',
				'amount_minor' => 5000,
				'currency'     => 'USD',
				'data'         => [ 'refunded_minor' => 1500 ],
			]
		);
		self::assertIsInt( $payment );

		Schema::install();

		self::assertSame( 1500, ( new Payment_Repository() )->find( $payment )['refunded_minor'] );
	}

	/**
	 * A currency-only URL still uses the 30-day default.
	 */
	public function test_default_range_filters_data(): void {
		$this->booking(
			[
				'currency'    => 'XTS',
				'created_utc' => gmdate( 'Y-m-d H:i:s', strtotime( '-5 days' ) ),
			]
		);
		$this->booking(
			[
				'currency'    => 'XTS',
				'created_utc' => gmdate( 'Y-m-d H:i:s', strtotime( '-40 days' ) ),
			]
		);

		$_GET['currency'] = 'XTS';

		ob_start();
		Statistics::render();
		$html = (string) ob_get_clean();

		self::assertMatchesRegularExpression( '/Total Bookings<\/span>\s*<strong class="reevents-stat-value">1<\/strong>/', $html );
	}

	/**
	 * The page defaults to the last 30 days.
	 */
	public function test_default_range(): void {
		ob_start();
		Statistics::render();
		$html = (string) ob_get_clean();

		$today = current_datetime()->format( 'Y-m-d' );
		$from  = current_datetime()->modify( '-29 days' )->format( 'Y-m-d' );

		self::assertStringContainsString( 'value="' . $from . '"', $html );
		self::assertStringContainsString( 'value="' . $today . '"', $html );
	}

	/**
	 * The admin page renders the metrics and chart.
	 */
	public function test_page(): void {
		$booking = $this->booking(
			[
				'quantity'    => 2,
				'total_minor' => 10000,
			]
		);
		$this->booking();

		( new Payment_Repository() )->create(
			[
				'booking_id'     => $booking,
				'status'         => 'succeeded',
				'amount_minor'   => 10000,
				'refunded_minor' => 2500,
				'currency'       => 'USD',
			]
		);

		$_GET['from']     = '2027-01-01';
		$_GET['to']       = '2027-01-31';
		$_GET['currency'] = 'USD';

		ob_start();
		Statistics::render();
		$html = (string) ob_get_clean();

		self::assertStringContainsString( 'Total Sales', $html );
		self::assertStringContainsString( '75.00 USD', $html );
		self::assertStringContainsString( 'Total Tickets', $html );
		self::assertStringContainsString( '3', $html );
		self::assertStringContainsString( '1.50', $html );
		self::assertStringContainsString( 'reevents-chart', $html );
	}

	/**
	 * Invalid dates show a notice and fall back to 30 days.
	 */
	public function test_invalid_date_notice(): void {
		$_GET['from'] = 'not-a-date';

		ob_start();
		Statistics::render();
		$html = (string) ob_get_clean();

		self::assertStringContainsString( 'One or more dates were invalid.', $html );
	}

	/**
	 * Clear query arguments.
	 */
	public function tear_down(): void {
		unset( $_GET['from'], $_GET['to'], $_GET['currency'], $_GET['all'] );
		update_option( 'timezone_string', '' );

		parent::tear_down();
	}
}

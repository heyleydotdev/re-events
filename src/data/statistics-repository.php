<?php
/**
 * Statistics repository.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Data;

use DateTimeImmutable;
use DateTimeZone;
use ReEvents\Services\Currency;

/**
 * Aggregate booking and sales statistics.
 */
final class Statistics_Repository {

	/**
	 * Bookings table.
	 */
	private function bookings_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'reevents_bookings';
	}

	/**
	 * Payments table.
	 */
	private function payments_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'reevents_payments';
	}

	/**
	 * Summary for one currency and an optional UTC date range.
	 *
	 * @param array<string, mixed> $args Currency, from and to.
	 * @return array{bookings:int,tickets:int,gross:int,refunds:int,sales:int}
	 */
	public function summary( array $args = [] ): array {
		global $wpdb;

		$currency = Currency::sanitize_code( (string) ( $args['currency'] ?? 'USD' ) );
		$where    = $this->where( $args, $currency );
		$bookings = $this->bookings_table();
		$payments = $this->payments_table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Aggregate over internal tables.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS bookings, COALESCE(SUM(b.quantity),0) AS tickets, COALESCE(SUM(b.total_minor),0) AS gross
				FROM {$bookings} b WHERE {$where['sql']}",
				...$where['values']
			),
			ARRAY_A
		);

		$refunds = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(p.refunded_minor),0) FROM {$payments} p
				INNER JOIN {$bookings} b ON b.id = p.booking_id
				WHERE {$where['sql']}",
				...$where['values']
			)
		);
		// phpcs:enable

		$gross = (int) ( $row['gross'] ?? 0 );

		return [
			'bookings' => (int) ( $row['bookings'] ?? 0 ),
			'tickets'  => (int) ( $row['tickets'] ?? 0 ),
			'gross'    => $gross,
			'refunds'  => $refunds,
			'sales'    => max( 0, $gross - $refunds ),
		];
	}

	/**
	 * Currencies that have confirmed bookings.
	 *
	 * @return string[]
	 */
	public function currencies(): array {
		global $wpdb;

		$table = $this->bookings_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table.
		$currencies = $wpdb->get_col( "SELECT DISTINCT currency FROM {$table} WHERE status = 'confirmed' ORDER BY currency ASC" );

		if ( ! is_array( $currencies ) ) {
			return [];
		}

		$currencies = array_values( array_unique( array_map( [ Currency::class, 'sanitize_code' ], $currencies ) ) );

		return $currencies;
	}

	/**
	 * Monthly net sales for the last months in the filtered range.
	 *
	 * @param array<string, mixed> $args Currency, from, to and months.
	 * @return array<int, array{month:string,sales:int}>
	 */
	public function monthly( array $args = [] ): array {
		global $wpdb;

		$currency = Currency::sanitize_code( (string) ( $args['currency'] ?? 'USD' ) );
		$months   = max( 1, min( 24, (int) ( $args['months'] ?? 12 ) ) );
		$bounds   = $this->month_bounds( $args, $months );

		if ( ! $bounds ) {
			return [];
		}

		$bookings = $this->bookings_table();
		$payments = $this->payments_table();
		$selects  = [];
		$values   = [];

		foreach ( $bounds as $bound ) {
			$selects[] = 'SELECT %s AS month, %s AS start_utc, %s AS end_utc';
			$values[]  = $bound['month'];
			$values[]  = $bound['start_utc'];
			$values[]  = $bound['end_utc'];
		}

		$month_sql = implode( ' UNION ALL ', $selects );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Aggregate over internal tables and generated month bounds.
		$gross_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.month, COALESCE(SUM(b.total_minor),0) AS total
				FROM ({$month_sql}) m
				LEFT JOIN {$bookings} b ON b.created_utc >= m.start_utc AND b.created_utc <= m.end_utc AND b.status = 'confirmed' AND b.currency = %s
				GROUP BY m.month ORDER BY m.month ASC",
				...array_merge( $values, [ $currency ] )
			),
			ARRAY_A
		);

		$refund_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.month, COALESCE(SUM(p.refunded_minor),0) AS total
				FROM ({$month_sql}) m
				LEFT JOIN {$bookings} b ON b.created_utc >= m.start_utc AND b.created_utc <= m.end_utc AND b.status = 'confirmed' AND b.currency = %s
				LEFT JOIN {$payments} p ON p.booking_id = b.id
				GROUP BY m.month ORDER BY m.month ASC",
				...array_merge( $values, [ $currency ] )
			),
			ARRAY_A
		);
		// phpcs:enable

		$gross  = [];
		$refund = [];

		foreach ( is_array( $gross_rows ) ? $gross_rows : [] as $row ) {
			$gross[ (string) $row['month'] ] = (int) $row['total'];
		}

		foreach ( is_array( $refund_rows ) ? $refund_rows : [] as $row ) {
			$refund[ (string) $row['month'] ] = (int) $row['total'];
		}

		$items = [];

		foreach ( $bounds as $bound ) {
			$month   = $bound['month'];
			$items[] = [
				'month' => $month,
				'sales' => max( 0, ( $gross[ $month ] ?? 0 ) - ( $refund[ $month ] ?? 0 ) ),
			];
		}

		return $items;
	}

	/**
	 * Build site-local month buckets in UTC.
	 *
	 * @param array<string, mixed> $args   Filters.
	 * @param int                  $months Maximum buckets.
	 * @return array<int, array{month:string,start_utc:string,end_utc:string}>
	 */
	private function month_bounds( array $args, int $months ): array {
		$timezone   = wp_timezone();
		$utc        = new DateTimeZone( 'UTC' );
		$from_local = self::utc_to_local( (string) ( $args['from'] ?? '' ), $timezone );
		$to_local   = self::utc_to_local( (string) ( $args['to'] ?? '' ), $timezone );
		$from_utc   = $from_local ? (string) $args['from'] : '';
		$to_utc     = $to_local ? (string) $args['to'] : '';
		$end_month  = ( $to_local ?? new DateTimeImmutable( 'now', $timezone ) )->modify( 'first day of this month' )->setTime( 0, 0 );
		$first      = $from_local ? $from_local->modify( 'first day of this month' )->setTime( 0, 0 ) : $end_month->modify( '-' . ( $months - 1 ) . ' months' );
		$earliest   = $end_month->modify( '-' . ( $months - 1 ) . ' months' );
		$start      = $first > $earliest ? $first : $earliest;

		if ( $start > $end_month ) {
			return [];
		}

		$bounds = [];

		for ( $cursor = $start; $cursor <= $end_month; $cursor = $cursor->modify( '+1 month' ) ) {
			$month_start = $cursor->setTime( 0, 0, 0 );
			$month_end   = $cursor->modify( 'last day of this month' )->setTime( 23, 59, 59 );
			$start_utc   = $month_start->setTimezone( $utc )->format( 'Y-m-d H:i:s' );
			$end_utc     = $month_end->setTimezone( $utc )->format( 'Y-m-d H:i:s' );

			if ( '' !== $from_utc && $from_utc > $start_utc ) {
				$start_utc = $from_utc;
			}

			if ( '' !== $to_utc && $to_utc < $end_utc ) {
				$end_utc = $to_utc;
			}

			$bounds[] = [
				'month'     => $cursor->format( 'Y-m' ),
				'start_utc' => $start_utc,
				'end_utc'   => $end_utc,
			];
		}

		return $bounds;
	}

	/**
	 * Convert a UTC datetime to the site time zone.
	 *
	 * @param string       $utc      UTC datetime.
	 * @param DateTimeZone $timezone Site time zone.
	 */
	private static function utc_to_local( string $utc, DateTimeZone $timezone ): ?DateTimeImmutable {
		if ( '' === $utc ) {
			return null;
		}

		$date = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $utc, new DateTimeZone( 'UTC' ) );

		return $date instanceof DateTimeImmutable ? $date->setTimezone( $timezone ) : null;
	}

	/**
	 * Build the shared WHERE clause.
	 *
	 * @param array<string, mixed> $args     Filters.
	 * @param string               $currency Currency code.
	 * @return array{sql:string,values:array<int, mixed>}
	 */
	private function where( array $args, string $currency ): array {
		$sql    = "b.status = 'confirmed' AND b.currency = %s";
		$values = [ $currency ];

		if ( '' !== (string) ( $args['from'] ?? '' ) ) {
			$sql     .= ' AND b.created_utc >= %s';
			$values[] = (string) $args['from'];
		}

		if ( '' !== (string) ( $args['to'] ?? '' ) ) {
			$sql     .= ' AND b.created_utc <= %s';
			$values[] = (string) $args['to'];
		}

		return [
			'sql'    => $sql,
			'values' => $values,
		];
	}
}

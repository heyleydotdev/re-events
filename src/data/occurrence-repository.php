<?php
/**
 * Occurrence repository.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Data;

/**
 * Reads and writes event occurrences.
 */
final class Occurrence_Repository {

	/**
	 * Table name.
	 */
	private function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'reevents_occurrences';
	}

	/**
	 * Delete unreferenced occurrences for an event.
	 *
	 * Occurrences with bookings are kept so booking history stays valid.
	 *
	 * @param int $event_id Event ID.
	 */
	public function delete_for_event( int $event_id ): void {
		global $wpdb;

		$table      = $this->table();
		$referenced = ( new Booking_Repository() )->occurrence_ids_for_event( $event_id );

		if ( $referenced ) {
			$placeholders = implode( ',', array_fill( 0, count( $referenced ), '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Internal table name and generated placeholders.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE event_id = %d AND id NOT IN ({$placeholders})", $event_id, ...$referenced ) );

			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Event occurrence storage.
		$wpdb->delete( $table, [ 'event_id' => $event_id ], [ '%d' ] );
	}

	/**
	 * Replace occurrences for an event, keeping existing row IDs.
	 *
	 * @param int                              $event_id    Event ID.
	 * @param array<int, array<string, mixed>> $occurrences Occurrence rows.
	 */
	public function replace_for_event( int $event_id, array $occurrences ): bool {
		global $wpdb;

		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Event occurrence storage.
		$wpdb->query( 'START TRANSACTION' );

		$existing = [];
		$seen     = [];

		foreach ( $this->get_all_for_event( $event_id ) as $row ) {
			$existing[ (string) $row['start_utc'] . '|' . (string) $row['timeslot_key'] ] = (int) $row['id'];
		}

		foreach ( $occurrences as $occurrence ) {
			$seen[ (string) ( $occurrence['start_utc'] ?? '' ) . '|' . (string) ( $occurrence['timeslot_key'] ?? '' ) ] = true;
		}

		foreach ( array_chunk( $occurrences, 200 ) as $chunk ) {
			$placeholders = [];
			$values       = [];

			foreach ( $chunk as $occurrence ) {
				$placeholders[] = '(%d,%s,%s,%s,%s,%d,%s)';
				array_push(
					$values,
					$event_id,
					(string) ( $occurrence['start_utc'] ?? '' ),
					(string) ( $occurrence['end_utc'] ?? '' ),
					(string) ( $occurrence['timezone'] ?? 'UTC' ),
					(string) ( $occurrence['timeslot_key'] ?? '' ),
					(int) ( $occurrence['capacity'] ?? 0 ),
					(string) ( $occurrence['status'] ?? 'scheduled' )
				);
			}

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Internal table and generated placeholders.
			// shortcut: VALUES() is deprecated on MySQL 8.0.20+ but still needed for MySQL 5.7/MariaDB; switch to a row alias when the database floor allows it.
			$result = $wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$table} (event_id,start_utc,end_utc,timezone,timeslot_key,capacity,status) VALUES " . implode( ',', $placeholders ) . ' ON DUPLICATE KEY UPDATE end_utc=VALUES(end_utc), timezone=VALUES(timezone), capacity=VALUES(capacity), status=VALUES(status)',
					...$values
				)
			);
			// phpcs:enable

			if ( false === $result ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Event occurrence storage.
				$wpdb->query( 'ROLLBACK' );
				return false;
			}
		}

		$referenced = ( new Booking_Repository() )->occurrence_ids_for_event( $event_id );

		foreach ( $existing as $key => $id ) {
			if ( ! isset( $seen[ $key ] ) ) {
				if ( in_array( $id, $referenced, true ) ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Event occurrence storage.
					$wpdb->update( $table, [ 'status' => 'cancelled' ], [ 'id' => $id ] );
				} else {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Event occurrence storage.
					$wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );
				}
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Event occurrence storage.
		$wpdb->query( 'COMMIT' );

		return true;
	}

	/**
	 * Find one occurrence.
	 *
	 * @param int $id Occurrence ID.
	 * @return array<string, mixed>|null
	 */
	public function find( int $id ): ?array {
		global $wpdb;

		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table name.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Reserve seats for an occurrence.
	 *
	 * @param int $id       Occurrence ID.
	 * @param int $quantity Seats.
	 */
	public function reserve( int $id, int $quantity ): bool {
		global $wpdb;

		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table name.
		$result = $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table name.
				"UPDATE {$table} SET sold = sold + %d WHERE id = %d AND status = 'scheduled' AND ( capacity = 0 OR sold + %d <= capacity )",
				max( 1, $quantity ),
				$id,
				max( 1, $quantity )
			)
		);

		return 1 === (int) $result;
	}

	/**
	 * Release seats for an occurrence.
	 *
	 * @param int $id       Occurrence ID.
	 * @param int $quantity Seats.
	 */
	public function release( int $id, int $quantity ): void {
		global $wpdb;

		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table name.
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table name.
				"UPDATE {$table} SET sold = GREATEST( 0, sold - %d ) WHERE id = %d",
				max( 1, $quantity ),
				$id
			)
		);
	}

	/**
	 * Get published occurrences in a UTC date range.
	 *
	 * @param string $from_utc Start UTC.
	 * @param string $to_utc   End UTC.
	 * @param int    $limit    Maximum rows.
	 * @param int    $offset   Offset.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_between( string $from_utc, string $to_utc, int $limit = 2000, int $offset = 0 ): array {
		global $wpdb;

		$table = $this->table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table name.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT o.* FROM {$table} o INNER JOIN {$wpdb->posts} p ON p.ID = o.event_id
				WHERE p.post_status = 'publish' AND o.status = 'scheduled' AND o.start_utc <= %s AND o.end_utc >= %s
				ORDER BY o.start_utc ASC LIMIT %d OFFSET %d",
				$to_utc,
				$from_utc,
				max( 1, $limit ),
				max( 0, $offset )
			),
			ARRAY_A
		);
		// phpcs:enable

		return is_array( $results ) ? $results : [];
	}

	/**
	 * Get event IDs with a published occurrence in a UTC date range.
	 *
	 * @param string $from_utc          Start UTC.
	 * @param string $to_utc            End UTC.
	 * @param int    $limit             Maximum event IDs.
	 * @param int    $offset            Offset.
	 * @param string $order             ASC or DESC.
	 * @param bool   $include_cancelled Include cancelled events.
	 * @return int[]
	 */
	public function event_ids_between( string $from_utc, string $to_utc, int $limit = 0, int $offset = 0, string $order = 'ASC', bool $include_cancelled = false ): array {
		global $wpdb;

		$table            = $this->table();
		$direction        = 'DESC' === strtoupper( $order ) ? 'DESC' : 'ASC';
		[ $join, $where ] = $this->status_filter( $include_cancelled );
		$sql              = "SELECT o.event_id FROM {$table} o INNER JOIN {$wpdb->posts} p ON p.ID = o.event_id {$join}
			WHERE p.post_status = 'publish' AND {$where} AND o.start_utc <= %s AND o.end_utc >= %s
			GROUP BY o.event_id ORDER BY MIN(o.start_utc) {$direction}";
		$args             = [ $to_utc, $from_utc ];

		if ( $limit > 0 ) {
			$sql   .= ' LIMIT %d OFFSET %d';
			$args[] = $limit;
			$args[] = max( 0, $offset );
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Internal table name and optional pagination.
		$ids = $wpdb->get_col( $wpdb->prepare( $sql, ...$args ) );
		// phpcs:enable

		return is_array( $ids ) ? array_map( 'intval', $ids ) : [];
	}

	/**
	 * Count events with a published occurrence in a UTC date range.
	 *
	 * @param string $from_utc          Start UTC.
	 * @param string $to_utc            End UTC.
	 * @param bool   $include_cancelled Include cancelled events.
	 */
	public function count_events_between( string $from_utc, string $to_utc, bool $include_cancelled = false ): int {
		global $wpdb;

		$table            = $this->table();
		[ $join, $where ] = $this->status_filter( $include_cancelled );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table name.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT o.event_id) FROM {$table} o INNER JOIN {$wpdb->posts} p ON p.ID = o.event_id {$join}
				WHERE p.post_status = 'publish' AND {$where} AND o.start_utc <= %s AND o.end_utc >= %s",
				$to_utc,
				$from_utc
			)
		);
		// phpcs:enable
	}

	/**
	 * SQL join and condition for the occurrence status filter.
	 *
	 * @param bool $include_cancelled Include cancelled events.
	 * @return array{0:string,1:string}
	 */
	private function status_filter( bool $include_cancelled ): array {
		global $wpdb;

		if ( ! $include_cancelled ) {
			return [ '', "o.status = 'scheduled'" ];
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table name.
		return [
			"LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = o.event_id AND pm.meta_key = '_reevents_status'",
			"( o.status = 'scheduled' OR ( o.status = 'cancelled' AND pm.meta_value = 'cancelled' ) )",
		];
	}

	/**
	 * Get every occurrence for an event.
	 *
	 * @param int $event_id Event ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_all_for_event( int $event_id ): array {
		global $wpdb;

		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table name.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table name.
				"SELECT * FROM {$table} WHERE event_id = %d ORDER BY start_utc ASC",
				$event_id
			),
			ARRAY_A
		);

		return is_array( $results ) ? $results : [];
	}

	/**
	 * Get occurrences for an event.
	 *
	 * @param int $event_id Event ID.
	 * @param int $limit    Maximum rows.
	 * @param int $offset   Offset.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_for_event( int $event_id, int $limit = 100, int $offset = 0 ): array {
		global $wpdb;

		$table = $this->table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table name.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE event_id = %d ORDER BY start_utc ASC LIMIT %d OFFSET %d",
				$event_id,
				max( 1, $limit ),
				max( 0, $offset )
			),
			ARRAY_A
		);
		// phpcs:enable

		return is_array( $results ) ? $results : [];
	}

	/**
	 * Count occurrences for an event.
	 *
	 * @param int $event_id Event ID.
	 */
	public function count_for_event( int $event_id ): int {
		global $wpdb;

		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table name.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE event_id = %d", $event_id ) );
	}
}

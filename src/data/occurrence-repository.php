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

		foreach ( $this->get_for_event( $event_id, 1000 ) as $row ) {
			$existing[ (string) $row['start_utc'] . '|' . (string) $row['timeslot_key'] ] = (int) $row['id'];
		}

		foreach ( $occurrences as $occurrence ) {
			$start_utc    = (string) ( $occurrence['start_utc'] ?? '' );
			$timeslot     = (string) ( $occurrence['timeslot_key'] ?? '' );
			$key          = $start_utc . '|' . $timeslot;
			$seen[ $key ] = true;
			$data         = [
				'end_utc'  => (string) ( $occurrence['end_utc'] ?? '' ),
				'timezone' => (string) ( $occurrence['timezone'] ?? 'UTC' ),
				'capacity' => (int) ( $occurrence['capacity'] ?? 0 ),
				'status'   => (string) ( $occurrence['status'] ?? 'scheduled' ),
			];

			if ( isset( $existing[ $key ] ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Event occurrence storage.
				$result = $wpdb->update( $table, $data, [ 'id' => $existing[ $key ] ] );
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Event occurrence storage.
				$result = $wpdb->insert(
					$table,
					$data + [
						'event_id'     => $event_id,
						'start_utc'    => $start_utc,
						'timeslot_key' => $timeslot,
					]
				);
			}

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
	 * @param string $from_utc Start UTC.
	 * @param string $to_utc   End UTC.
	 * @param int    $limit    Maximum event IDs.
	 * @param int    $offset   Offset.
	 * @return int[]
	 */
	public function event_ids_between( string $from_utc, string $to_utc, int $limit = 0, int $offset = 0 ): array {
		global $wpdb;

		$table = $this->table();
		$sql   = "SELECT o.event_id FROM {$table} o INNER JOIN {$wpdb->posts} p ON p.ID = o.event_id
			WHERE p.post_status = 'publish' AND o.status = 'scheduled' AND o.start_utc <= %s AND o.end_utc >= %s
			GROUP BY o.event_id ORDER BY MIN(o.start_utc) ASC";
		$args  = [ $to_utc, $from_utc ];

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
	 * @param string $from_utc Start UTC.
	 * @param string $to_utc   End UTC.
	 */
	public function count_events_between( string $from_utc, string $to_utc ): int {
		global $wpdb;

		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table name.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table name.
				"SELECT COUNT(DISTINCT o.event_id) FROM {$table} o INNER JOIN {$wpdb->posts} p ON p.ID = o.event_id
				WHERE p.post_status = 'publish' AND o.status = 'scheduled' AND o.start_utc <= %s AND o.end_utc >= %s",
				$to_utc,
				$from_utc
			)
		);
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

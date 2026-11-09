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
	 * Delete occurrences for an event.
	 *
	 * @param int $event_id Event ID.
	 */
	public function delete_for_event( int $event_id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Event occurrence storage.
		$wpdb->delete( $this->table(), [ 'event_id' => $event_id ], [ '%d' ] );
	}

	/**
	 * Replace occurrences for an event.
	 *
	 * @param int                              $event_id    Event ID.
	 * @param array<int, array<string, mixed>> $occurrences Occurrence rows.
	 */
	public function replace_for_event( int $event_id, array $occurrences ): void {
		global $wpdb;

		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Event occurrence storage.
		$wpdb->query( 'START TRANSACTION' );

		$this->delete_for_event( $event_id );

		if ( $occurrences ) {
			$values       = [];
			$placeholders = [];

			foreach ( $occurrences as $occurrence ) {
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

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Internal table name and generated placeholders.
			$result = $wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$table} (event_id,start_utc,end_utc,timezone,timeslot_key,capacity,status) VALUES " . implode( ',', $placeholders ),
					...$values
				)
			);
			// phpcs:enable

			if ( false === $result ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Event occurrence storage.
				$wpdb->query( 'ROLLBACK' );
				return;
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Event occurrence storage.
		$wpdb->query( 'COMMIT' );

		// shortcut: row IDs change on every sync; switch to upsert before bookings reference occurrence_id.
	}

	/**
	 * Get published occurrences in a UTC date range.
	 *
	 * @param string $from_utc Start UTC.
	 * @param string $to_utc   End UTC.
	 * @param int    $limit    Maximum rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_between( string $from_utc, string $to_utc, int $limit = 2000 ): array {
		global $wpdb;

		$table = $this->table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table name.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT o.* FROM {$table} o INNER JOIN {$wpdb->posts} p ON p.ID = o.event_id
				WHERE p.post_status = 'publish' AND o.start_utc >= %s AND o.start_utc <= %s
				ORDER BY o.start_utc ASC LIMIT %d",
				$from_utc,
				$to_utc,
				max( 1, $limit )
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
	 * @return int[]
	 */
	public function event_ids_between( string $from_utc, string $to_utc ): array {
		global $wpdb;

		$table = $this->table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table name.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT o.event_id FROM {$table} o INNER JOIN {$wpdb->posts} p ON p.ID = o.event_id
				WHERE p.post_status = 'publish' AND o.start_utc >= %s AND o.start_utc <= %s
				GROUP BY o.event_id ORDER BY MIN(o.start_utc) ASC",
				$from_utc,
				$to_utc
			)
		);
		// phpcs:enable

		return is_array( $ids ) ? array_map( 'intval', $ids ) : [];
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

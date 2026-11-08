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

		$this->delete_for_event( $event_id );

		foreach ( $occurrences as $occurrence ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Event occurrence storage.
			$wpdb->insert(
				$this->table(),
				[
					'event_id'     => $event_id,
					'start_utc'    => (string) ( $occurrence['start_utc'] ?? '' ),
					'end_utc'      => (string) ( $occurrence['end_utc'] ?? '' ),
					'timezone'     => (string) ( $occurrence['timezone'] ?? 'UTC' ),
					'timeslot_key' => (string) ( $occurrence['timeslot_key'] ?? '' ),
					'capacity'     => (int) ( $occurrence['capacity'] ?? 0 ),
					'status'       => (string) ( $occurrence['status'] ?? 'scheduled' ),
				],
				[ '%d', '%s', '%s', '%s', '%s', '%d', '%s' ]
			);
		}
	}

	/**
	 * Get occurrences in a UTC date range.
	 *
	 * @param string $from_utc Start UTC.
	 * @param string $to_utc   End UTC.
	 * @param int    $limit    Maximum rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_between( string $from_utc, string $to_utc, int $limit = 100 ): array {
		global $wpdb;

		$table = $this->table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table name.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE start_utc >= %s AND start_utc <= %s ORDER BY start_utc ASC LIMIT %d",
				$from_utc,
				$to_utc,
				max( 1, $limit )
			),
			ARRAY_A
		);
		// phpcs:enable

		return is_array( $results ) ? $results : [];
	}
}

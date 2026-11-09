<?php
/**
 * Booking repository.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Data;

use WP_Error;

/**
 * Reads and writes bookings and attendees.
 */
final class Booking_Repository {

	/**
	 * Bookings table.
	 */
	private function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'reevents_bookings';
	}

	/**
	 * Attendees table.
	 */
	private function attendees_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'reevents_attendees';
	}

	/**
	 * Find a booking.
	 *
	 * @param int $id Booking ID.
	 * @return array<string, mixed>|null
	 */
	public function find( int $id ): ?array {
		global $wpdb;

		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table name.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );

		return is_array( $row ) ? $this->hydrate( $row ) : null;
	}

	/**
	 * Get attendees for a booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function attendees( int $booking_id ): array {
		global $wpdb;

		$table = $this->attendees_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table name.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE booking_id = %d ORDER BY id ASC", $booking_id ), ARRAY_A );

		$attendees = [];

		foreach ( (array) $rows as $row ) {
			$attendees[] = [
				'id'         => (int) ( $row['id'] ?? 0 ),
				'booking_id' => (int) ( $row['booking_id'] ?? 0 ),
				'ticket_id'  => (int) ( $row['ticket_id'] ?? 0 ),
				'name'       => (string) ( $row['name'] ?? '' ),
				'email'      => (string) ( $row['email'] ?? '' ),
				'fields'     => json_decode( (string) ( $row['fields_json'] ?? '[]' ), true ),
				'checked_in' => (bool) ( $row['checked_in'] ?? false ),
			];
		}

		return $attendees;
	}

	/**
	 * Create a booking.
	 *
	 * @param array<string, mixed> $booking Booking data.
	 * @return int|WP_Error
	 */
	public function create( array $booking ) {
		global $wpdb;

		$data = [
			'reference'     => '' !== (string) ( $booking['reference'] ?? '' ) ? (string) $booking['reference'] : null,
			'event_id'      => (int) ( $booking['event_id'] ?? 0 ),
			'occurrence_id' => (int) ( $booking['occurrence_id'] ?? 0 ),
			'ticket_id'     => (int) ( $booking['ticket_id'] ?? 0 ),
			'user_id'       => (int) ( $booking['user_id'] ?? 0 ),
			'quantity'      => max( 1, (int) ( $booking['quantity'] ?? 1 ) ),
			'status'        => (string) ( $booking['status'] ?? 'pending' ),
			'total_minor'   => max( 0, (int) ( $booking['total_minor'] ?? 0 ) ),
			'currency'      => (string) ( $booking['currency'] ?? 'USD' ),
			'gateway'       => (string) ( $booking['gateway'] ?? '' ),
			'gateway_ref'   => (string) ( $booking['gateway_ref'] ?? '' ),
			'created_utc'   => (string) ( $booking['created_utc'] ?? current_time( 'mysql', true ) ),
			'updated_utc'   => (string) ( $booking['updated_utc'] ?? current_time( 'mysql', true ) ),
		];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Booking storage.
		$result = $wpdb->insert( $this->table(), $data, [ '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s' ] );

		return false === $result ? new WP_Error( 'reevents_booking_save_failed', __( 'The booking could not be saved.', 're-events' ), [ 'status' => 500 ] ) : (int) $wpdb->insert_id;
	}

	/**
	 * Find a booking by its reference.
	 *
	 * @param string $reference Reference.
	 * @return array<string, mixed>|null
	 */
	public function find_by_reference( string $reference ): ?array {
		global $wpdb;

		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table name.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE reference = %s", $reference ), ARRAY_A );

		return is_array( $row ) ? $this->hydrate( $row ) : null;
	}

	/**
	 * Get bookings for an event.
	 *
	 * @param int    $event_id Event ID.
	 * @param string $status   Optional status.
	 * @param int    $limit    Maximum rows.
	 * @param int    $offset   Offset.
	 * @return array<int, array<string, mixed>>
	 */
	public function for_event( int $event_id, string $status = '', int $limit = 100, int $offset = 0 ): array {
		global $wpdb;

		$table = $this->table();
		$sql   = "SELECT * FROM {$table} WHERE event_id = %d";
		$args  = [ $event_id ];

		if ( '' !== $status ) {
			$sql   .= ' AND status = %s';
			$args[] = $status;
		}

		$sql   .= ' ORDER BY created_utc DESC LIMIT %d OFFSET %d';
		$args[] = max( 1, $limit );
		$args[] = max( 0, $offset );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Internal table name and dynamic filters.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$args ), ARRAY_A );
		// phpcs:enable

		$bookings = [];

		foreach ( (array) $rows as $row ) {
			$bookings[] = $this->hydrate( $row );
		}

		return $bookings;
	}

	/**
	 * Count bookings for an event.
	 *
	 * @param int    $event_id Event ID.
	 * @param string $status   Optional status.
	 */
	public function count_for_event( int $event_id, string $status = '' ): int {
		global $wpdb;

		$table = $this->table();
		$sql   = "SELECT COUNT(*) FROM {$table} WHERE event_id = %d";
		$args  = [ $event_id ];

		if ( '' !== $status ) {
			$sql   .= ' AND status = %s';
			$args[] = $status;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Internal table name and dynamic filters.
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, ...$args ) );
		// phpcs:enable
	}

	/**
	 * Add an attendee.
	 *
	 * @param int                  $booking_id Booking ID.
	 * @param array<string, mixed> $attendee   Attendee data.
	 * @return int|WP_Error
	 */
	public function add_attendee( int $booking_id, array $attendee ) {
		global $wpdb;

		$data = [
			'booking_id'  => $booking_id,
			'ticket_id'   => (int) ( $attendee['ticket_id'] ?? 0 ),
			'name'        => sanitize_text_field( (string) ( $attendee['name'] ?? '' ) ),
			'email'       => sanitize_email( (string) ( $attendee['email'] ?? '' ) ),
			'fields_json' => (string) wp_json_encode( (array) ( $attendee['fields'] ?? [] ) ),
			'checked_in'  => ! empty( $attendee['checked_in'] ) ? 1 : 0,
		];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Attendee storage.
		$result = $wpdb->insert( $this->attendees_table(), $data, [ '%d', '%d', '%s', '%s', '%s', '%d' ] );

		return false === $result ? new WP_Error( 'reevents_attendee_save_failed', __( 'The attendee could not be saved.', 're-events' ), [ 'status' => 500 ] ) : (int) $wpdb->insert_id;
	}

	/**
	 * Update a booking status.
	 *
	 * @param int    $id     Booking ID.
	 * @param string $status New status.
	 */
	public function update_status( int $id, string $status ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Booking storage.
		$result = $wpdb->update(
			$this->table(),
			[
				'status'      => $status,
				'updated_utc' => current_time( 'mysql', true ),
			],
			[ 'id' => $id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);

		return false !== $result;
	}

	/**
	 * Delete a booking and its attendees.
	 *
	 * @param int $id Booking ID.
	 */
	public function delete( int $id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Booking storage.
		$wpdb->delete( $this->attendees_table(), [ 'booking_id' => $id ], [ '%d' ] );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Booking storage.
		$result = $wpdb->delete( $this->table(), [ 'id' => $id ], [ '%d' ] );

		return false !== $result;
	}

	/**
	 * Hydrate a booking row.
	 *
	 * @param array<string, mixed> $row Database row.
	 * @return array<string, mixed>
	 */
	private function hydrate( array $row ): array {
		return [
			'id'            => (int) ( $row['id'] ?? 0 ),
			'reference'     => (string) ( $row['reference'] ?? '' ),
			'event_id'      => (int) ( $row['event_id'] ?? 0 ),
			'occurrence_id' => (int) ( $row['occurrence_id'] ?? 0 ),
			'ticket_id'     => (int) ( $row['ticket_id'] ?? 0 ),
			'user_id'       => (int) ( $row['user_id'] ?? 0 ),
			'quantity'      => (int) ( $row['quantity'] ?? 1 ),
			'status'        => (string) ( $row['status'] ?? 'pending' ),
			'total_minor'   => (int) ( $row['total_minor'] ?? 0 ),
			'currency'      => (string) ( $row['currency'] ?? 'USD' ),
			'gateway'       => (string) ( $row['gateway'] ?? '' ),
			'gateway_ref'   => (string) ( $row['gateway_ref'] ?? '' ),
			'created_utc'   => (string) ( $row['created_utc'] ?? '' ),
			'updated_utc'   => (string) ( $row['updated_utc'] ?? '' ),
		];
	}
}

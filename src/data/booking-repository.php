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

		$fields = [];

		foreach ( (array) ( $attendee['fields'] ?? [] ) as $field_key => $field_value ) {
			if ( is_scalar( $field_value ) ) {
				$fields[ sanitize_key( (string) $field_key ) ] = sanitize_text_field( (string) $field_value );
			}
		}

		$data = [
			'booking_id'  => $booking_id,
			'ticket_id'   => (int) ( $attendee['ticket_id'] ?? 0 ),
			'name'        => sanitize_text_field( (string) ( $attendee['name'] ?? '' ) ),
			'email'       => sanitize_email( (string) ( $attendee['email'] ?? '' ) ),
			'fields_json' => (string) wp_json_encode( $fields ),
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
	 * Mark attendees as checked in or out.
	 *
	 * @param int[] $ids     Attendee IDs.
	 * @param bool  $checked Checked in.
	 */
	public function set_checked_in( array $ids, bool $checked ): int {
		global $wpdb;

		$ids = array_values( array_unique( array_map( 'absint', $ids ) ) );

		if ( ! $ids ) {
			return 0;
		}

		$table        = $this->attendees_table();
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$args         = array_merge( [ $checked ? 1 : 0 ], $ids );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Internal table and generated placeholders.
		return (int) $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET checked_in = %d WHERE id IN ({$placeholders})", ...$args ) );
		// phpcs:enable
	}

	/**
	 * Get pending booking IDs older than a cutoff.
	 *
	 * @param string $cutoff UTC cutoff.
	 * @return int[]
	 */
	public function pending_before( string $cutoff ): array {
		global $wpdb;

		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table name.
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE status = 'pending' AND created_utc < %s", $cutoff ) );

		return is_array( $ids ) ? array_map( 'intval', $ids ) : [];
	}

	/**
	 * Query attendees with pagination.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 * @return array{items:array<int, array<string, mixed>>,total:int}
	 */
	public function attendees_query( array $args = [] ): array {
		global $wpdb;

		$bookings  = $this->table();
		$attendees = $this->attendees_table();
		$tickets   = $wpdb->prefix . 'reevents_tickets';
		$event_id  = (int) ( $args['event_id'] ?? 0 );
		$search    = (string) ( $args['search'] ?? '' );
		$limit     = max( 1, (int) ( $args['limit'] ?? 20 ) );
		$offset    = max( 0, (int) ( $args['offset'] ?? 0 ) );
		$order     = 'asc' === strtolower( (string) ( $args['order'] ?? 'desc' ) ) ? 'ASC' : 'DESC';
		$columns   = [
			'name'      => 'a.name',
			'email'     => 'a.email',
			'status'    => 'b.status',
			'reference' => 'b.reference',
			'created'   => 'b.created_utc',
		];
		$orderby   = $columns[ (string) ( $args['orderby'] ?? 'created' ) ] ?? 'b.created_utc';
		$where     = '( %d = 0 OR b.event_id = %d )';
		$values    = [ $event_id, $event_id ];

		if ( '' !== $search ) {
			$where   .= ' AND ( a.name LIKE %s OR a.email LIKE %s OR b.reference LIKE %s )';
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}

		$from = "FROM {$attendees} a INNER JOIN {$bookings} b ON b.id = a.booking_id LEFT JOIN {$tickets} t ON t.id = a.ticket_id";

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Internal tables and whitelisted order.
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) {$from} WHERE {$where}", ...$values ) );

		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.id, a.booking_id, a.name, a.email, a.fields_json, a.checked_in, b.reference, b.status AS booking_status, b.event_id, b.created_utc, t.name AS ticket_name
				{$from} WHERE {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
				...array_merge( $values, [ $limit, $offset ] )
			),
			ARRAY_A
		);
		// phpcs:enable

		return [
			'items' => is_array( $items ) ? $items : [],
			'total' => $total,
		];
	}

	/**
	 * Query bookings with pagination.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 * @return array{items:array<int, array<string, mixed>>,total:int}
	 */
	public function query( array $args = [] ): array {
		global $wpdb;

		$table    = $this->table();
		$event_id = (int) ( $args['event_id'] ?? 0 );
		$status   = (string) ( $args['status'] ?? '' );
		$search   = (string) ( $args['search'] ?? '' );
		$limit    = max( 1, (int) ( $args['limit'] ?? 20 ) );
		$offset   = max( 0, (int) ( $args['offset'] ?? 0 ) );
		$orderby  = (string) ( $args['orderby'] ?? 'created' );
		$order    = 'asc' === strtolower( (string) ( $args['order'] ?? 'desc' ) ) ? 'ASC' : 'DESC';
		$columns  = [
			'created' => 'b.created_utc',
			'event'   => 'p.post_title',
		];
		$sort     = $columns[ $orderby ] ?? $columns['created'];
		$where    = '( %d = 0 OR b.event_id = %d )';
		$values   = [ $event_id, $event_id ];

		if ( '' !== $status ) {
			$where   .= ' AND b.status = %s';
			$values[] = $status;
		}

		if ( '' !== $search ) {
			$where   .= ' AND b.reference LIKE %s';
			$values[] = '%' . $wpdb->esc_like( $search ) . '%';
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Internal table and dynamic filters.
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} b WHERE {$where}", ...$values ) );

		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT b.*, p.post_title AS event_title FROM {$table} b LEFT JOIN {$wpdb->posts} p ON p.ID = b.event_id WHERE {$where} ORDER BY {$sort} {$order} LIMIT %d OFFSET %d",
				...array_merge( $values, [ $limit, $offset ] )
			),
			ARRAY_A
		);
		// phpcs:enable

		$bookings = [];

		foreach ( (array) $items as $row ) {
			$booking                = $this->hydrate( $row );
			$booking['event_title'] = (string) ( $row['event_title'] ?? '' );
			$bookings[]             = $booking;
		}

		return [
			'items' => $bookings,
			'total' => $total,
		];
	}

	/**
	 * Get attendees for an event export.
	 *
	 * @param int $event_id Event ID, or 0 for all.
	 * @return array<int, array<string, mixed>>
	 */
	public function attendees_for_event( int $event_id = 0 ): array {
		global $wpdb;

		$bookings  = $this->table();
		$attendees = $this->attendees_table();
		$tickets   = $wpdb->prefix . 'reevents_tickets';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal tables.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.id, a.booking_id, a.name, a.email, a.fields_json, a.checked_in, b.reference, b.status AS booking_status, b.event_id, b.created_utc, t.name AS ticket_name
				FROM {$attendees} a
				INNER JOIN {$bookings} b ON b.id = a.booking_id
				LEFT JOIN {$tickets} t ON t.id = a.ticket_id
				WHERE ( %d = 0 OR b.event_id = %d )
				ORDER BY b.created_utc DESC, a.id ASC",
				$event_id,
				$event_id
			),
			ARRAY_A
		);
		// phpcs:enable

		return is_array( $rows ) ? $rows : [];
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

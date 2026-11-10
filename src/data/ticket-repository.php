<?php
/**
 * Ticket repository.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Data;

use ReEvents\Services\Currency;
use WP_Error;

/**
 * Reads and writes event tickets.
 */
final class Ticket_Repository {

	/**
	 * Table name.
	 */
	private function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'reevents_tickets';
	}

	/**
	 * Find a ticket.
	 *
	 * @param int $id Ticket ID.
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
	 * Get tickets for an event.
	 *
	 * @param int    $event_id Event ID.
	 * @param string $status   Optional status.
	 * @return array<int, array<string, mixed>>
	 */
	public function for_event( int $event_id, string $status = '' ): array {
		global $wpdb;

		$table = $this->table();
		$sql   = "SELECT * FROM {$table} WHERE event_id = %d";
		$args  = [ $event_id ];

		if ( '' !== $status ) {
			$sql   .= ' AND status = %s';
			$args[] = $status;
		}

		$sql .= ' ORDER BY price_minor ASC, id ASC';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Internal table name and dynamic filters.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$args ), ARRAY_A );
		// phpcs:enable

		$tickets = [];

		foreach ( (array) $rows as $row ) {
			$tickets[] = $this->hydrate( $row );
		}

		return $tickets;
	}

	/**
	 * Save a ticket.
	 *
	 * @param array<string, mixed> $ticket Ticket data.
	 * @return int|WP_Error
	 */
	public function save( array $ticket ) {
		global $wpdb;

		$id         = (int) ( $ticket['id'] ?? 0 );
		$sale_start = Meta::sanitize_datetime( $ticket['sale_start_utc'] ?? '' );
		$sale_end   = Meta::sanitize_datetime( $ticket['sale_end_utc'] ?? '' );
		$data       = [
			'event_id'        => (int) ( $ticket['event_id'] ?? 0 ),
			'name'            => sanitize_text_field( (string) ( $ticket['name'] ?? '' ) ),
			'description'     => sanitize_textarea_field( (string) ( $ticket['description'] ?? '' ) ),
			'price_minor'     => max( 0, (int) ( $ticket['price_minor'] ?? 0 ) ),
			'currency'        => Currency::sanitize_code( (string) ( $ticket['currency'] ?? 'USD' ) ),
			'capacity'        => max( 0, (int) ( $ticket['capacity'] ?? 0 ) ),
			'min_per_booking' => max( 1, (int) ( $ticket['min_per_booking'] ?? 1 ) ),
			'max_per_booking' => max( 0, (int) ( $ticket['max_per_booking'] ?? 0 ) ),
			'sale_start_utc'  => '' !== $sale_start ? $sale_start : null,
			'sale_end_utc'    => '' !== $sale_end ? $sale_end : null,
			'status'          => in_array( $ticket['status'] ?? '', [ 'active', 'inactive' ], true ) ? (string) $ticket['status'] : 'active',
		];

		if ( '' === $data['name'] || ! $data['event_id'] ) {
			return new WP_Error( 'reevents_ticket_invalid', __( 'A ticket name and event are required.', 're-events' ), [ 'status' => 400 ] );
		}

		if ( $data['max_per_booking'] > 0 && $data['max_per_booking'] < $data['min_per_booking'] ) {
			return new WP_Error( 'reevents_ticket_invalid_quantity', __( 'The maximum seats must not be below the minimum seats.', 're-events' ), [ 'status' => 400 ] );
		}

		if ( null !== $data['sale_start_utc'] && null !== $data['sale_end_utc'] && $data['sale_end_utc'] < $data['sale_start_utc'] ) {
			return new WP_Error( 'reevents_ticket_invalid_sale_window', __( 'Ticket sales must end after they start.', 're-events' ), [ 'status' => 400 ] );
		}

		$formats = [ '%d', '%s', '%s', '%d', '%s', '%d', '%d', '%d', '%s', '%s', '%s' ];

		if ( $id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Ticket storage.
			$result = $wpdb->update( $this->table(), $data, [ 'id' => $id ], $formats, [ '%d' ] );

			return false === $result ? new WP_Error( 'reevents_ticket_save_failed', __( 'The ticket could not be saved.', 're-events' ), [ 'status' => 500 ] ) : $id;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Ticket storage.
		$result = $wpdb->insert( $this->table(), $data, $formats );

		return false === $result ? new WP_Error( 'reevents_ticket_save_failed', __( 'The ticket could not be saved.', 're-events' ), [ 'status' => 500 ] ) : (int) $wpdb->insert_id;
	}

	/**
	 * Delete a ticket.
	 *
	 * @param int $id Ticket ID.
	 */
	public function delete( int $id ): bool {
		global $wpdb;

		if ( ( new Booking_Repository() )->count_for_ticket( $id ) > 0 ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Ticket storage.
		$result = $wpdb->delete( $this->table(), [ 'id' => $id ], [ '%d' ] );

		return false !== $result;
	}

	/**
	 * Reserve seats atomically.
	 *
	 * @param int $ticket_id Ticket ID.
	 * @param int $quantity  Seats.
	 */
	public function reserve( int $ticket_id, int $quantity ): bool {
		global $wpdb;

		$quantity = max( 1, $quantity );
		$table    = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table name.
		$result = $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table name.
				"UPDATE {$table} SET sold = sold + %d WHERE id = %d AND status = 'active' AND ( capacity = 0 OR sold + %d <= capacity )",
				$quantity,
				$ticket_id,
				$quantity
			)
		);

		return 1 === (int) $result;
	}

	/**
	 * Release seats.
	 *
	 * @param int $ticket_id Ticket ID.
	 * @param int $quantity  Seats.
	 */
	public function release( int $ticket_id, int $quantity ): void {
		global $wpdb;

		$quantity = max( 1, $quantity );
		$table    = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table name.
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table name.
				"UPDATE {$table} SET sold = GREATEST( 0, sold - %d ) WHERE id = %d",
				$quantity,
				$ticket_id
			)
		);
	}

	/**
	 * Hydrate a ticket row.
	 *
	 * @param array<string, mixed> $row Database row.
	 * @return array<string, mixed>
	 */
	private function hydrate( array $row ): array {
		return [
			'id'              => (int) ( $row['id'] ?? 0 ),
			'event_id'        => (int) ( $row['event_id'] ?? 0 ),
			'name'            => (string) ( $row['name'] ?? '' ),
			'description'     => (string) ( $row['description'] ?? '' ),
			'price_minor'     => (int) ( $row['price_minor'] ?? 0 ),
			'currency'        => (string) ( $row['currency'] ?? 'USD' ),
			'capacity'        => (int) ( $row['capacity'] ?? 0 ),
			'sold'            => (int) ( $row['sold'] ?? 0 ),
			'min_per_booking' => (int) ( $row['min_per_booking'] ?? 1 ),
			'max_per_booking' => (int) ( $row['max_per_booking'] ?? 0 ),
			'sale_start_utc'  => (string) ( $row['sale_start_utc'] ?? '' ),
			'sale_end_utc'    => (string) ( $row['sale_end_utc'] ?? '' ),
			'status'          => (string) ( $row['status'] ?? 'active' ),
		];
	}
}

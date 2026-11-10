<?php
/**
 * Payment repository.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Data;

use ReEvents\Services\Currency;
use WP_Error;

/**
 * Reads and writes payment records.
 */
final class Payment_Repository {

	/**
	 * Table name.
	 */
	private function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'reevents_payments';
	}

	/**
	 * Create a payment.
	 *
	 * @param array<string, mixed> $payment Payment data.
	 * @return int|WP_Error
	 */
	public function create( array $payment ) {
		global $wpdb;

		$currency = Currency::sanitize_code( (string) ( $payment['currency'] ?? 'USD' ) );

		$data = [
			'booking_id'   => (int) ( $payment['booking_id'] ?? 0 ),
			'gateway'      => sanitize_key( (string) ( $payment['gateway'] ?? '' ) ),
			'gateway_ref'  => sanitize_text_field( (string) ( $payment['gateway_ref'] ?? '' ) ),
			'status'       => sanitize_key( (string) ( $payment['status'] ?? 'pending' ) ),
			'amount_minor' => max( 0, (int) ( $payment['amount_minor'] ?? 0 ) ),
			'currency'     => $currency,
			'data_json'    => wp_json_encode( (array) ( $payment['data'] ?? [] ) ),
			'created_utc'  => (string) ( $payment['created_utc'] ?? current_time( 'mysql', true ) ),
			'updated_utc'  => (string) ( $payment['updated_utc'] ?? current_time( 'mysql', true ) ),
		];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Payment storage.
		$result = $wpdb->insert( $this->table(), $data, [ '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' ] );

		return false === $result ? new WP_Error( 'reevents_payment_save_failed', __( 'The payment could not be saved.', 're-events' ), [ 'status' => 500 ] ) : (int) $wpdb->insert_id;
	}

	/**
	 * Find a payment.
	 *
	 * @param int $id Payment ID.
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
	 * Find a payment by gateway reference.
	 *
	 * @param string $gateway     Gateway ID.
	 * @param string $gateway_ref Gateway reference.
	 * @return array<string, mixed>|null
	 */
	public function find_by_ref( string $gateway, string $gateway_ref ): ?array {
		global $wpdb;

		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table name.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE gateway = %s AND gateway_ref = %s ORDER BY id DESC LIMIT 1", $gateway, $gateway_ref ), ARRAY_A );

		return is_array( $row ) ? $this->hydrate( $row ) : null;
	}

	/**
	 * Find a payment by a stored gateway intent.
	 *
	 * @param string $intent Payment intent or order reference stored in data.
	 * @return array<string, mixed>|null
	 */
	public function find_by_intent( string $intent ): ?array {
		global $wpdb;

		if ( '' === $intent ) {
			return null;
		}

		$table = $this->table();
		$like  = '%"payment_intent":"' . $wpdb->esc_like( $intent ) . '"%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Payment lookup.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE data_json LIKE %s ORDER BY id DESC LIMIT 1", $like ), ARRAY_A );

		return is_array( $row ) ? $this->hydrate( $row ) : null;
	}

	/**
	 * Get payments for a booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function for_booking( int $booking_id ): array {
		global $wpdb;

		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table name.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE booking_id = %d ORDER BY id ASC", $booking_id ), ARRAY_A );

		return array_map( [ $this, 'hydrate' ], is_array( $rows ) ? $rows : [] );
	}

	/**
	 * Update a payment.
	 *
	 * @param int                  $id   Payment ID.
	 * @param array<string, mixed> $data Fields.
	 */
	public function update( int $id, array $data ): bool {
		global $wpdb;

		$fields = [];

		if ( isset( $data['gateway_ref'] ) ) {
			$fields['gateway_ref'] = sanitize_text_field( (string) $data['gateway_ref'] );
		}

		if ( isset( $data['status'] ) ) {
			$fields['status'] = sanitize_key( (string) $data['status'] );
		}

		if ( isset( $data['data'] ) ) {
			$fields['data_json'] = wp_json_encode( (array) $data['data'] );
		}

		if ( ! $fields ) {
			return false;
		}

		$fields['updated_utc'] = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Payment storage.
		$result = $wpdb->update( $this->table(), $fields, [ 'id' => $id ] );

		return false !== $result;
	}

	/**
	 * Hydrate a payment row.
	 *
	 * @param array<string, mixed> $row Database row.
	 * @return array<string, mixed>
	 */
	private function hydrate( array $row ): array {
		$data = json_decode( (string) ( $row['data_json'] ?? '[]' ), true );

		return [
			'id'           => (int) ( $row['id'] ?? 0 ),
			'booking_id'   => (int) ( $row['booking_id'] ?? 0 ),
			'gateway'      => (string) ( $row['gateway'] ?? '' ),
			'gateway_ref'  => (string) ( $row['gateway_ref'] ?? '' ),
			'status'       => (string) ( $row['status'] ?? 'pending' ),
			'amount_minor' => (int) ( $row['amount_minor'] ?? 0 ),
			'currency'     => (string) ( $row['currency'] ?? 'USD' ),
			'data'         => is_array( $data ) ? $data : [],
			'created_utc'  => (string) ( $row['created_utc'] ?? '' ),
			'updated_utc'  => (string) ( $row['updated_utc'] ?? '' ),
		];
	}
}

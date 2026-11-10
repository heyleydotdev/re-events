<?php
/**
 * Attendee export rows.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Services;

use ReEvents\Data\Booking_Repository;

/**
 * Builds attendee export rows.
 */
final class Attendee_Export {

	/**
	 * CSV header.
	 *
	 * @return string[]
	 */
	public static function header(): array {
		return [ 'Booking', 'Reference', 'Status', 'Event', 'Ticket', 'Name', 'Email', 'Fields', 'Checked in', 'Created' ];
	}

	/**
	 * Stream export rows in batches.
	 *
	 * @param int      $event_id Event ID, or 0 for all.
	 * @param callable $callback Receives one row.
	 */
	public static function each( int $event_id, callable $callback ): void {
		$repo   = new Booking_Repository();
		$offset = 0;
		$batch  = 500;

		do {
			$records = $repo->attendees_for_event( $event_id, $batch, $offset );

			foreach ( $records as $record ) {
				$callback( self::row( $record ) );
			}

			$offset += $batch;
			$count   = count( $records );
		} while ( $count === $batch );
	}

	/**
	 * Build one export row.
	 *
	 * @param array<string, mixed> $record Attendee record.
	 * @return string[]
	 */
	private static function row( array $record ): array {
		return [
			(string) ( $record['booking_id'] ?? '' ),
			(string) ( $record['reference'] ?? '' ),
			(string) ( $record['booking_status'] ?? '' ),
			(string) ( $record['event_title'] ?? '' ),
			(string) ( $record['ticket_name'] ?? '' ),
			(string) ( $record['name'] ?? '' ),
			(string) ( $record['email'] ?? '' ),
			self::fields( $record['fields_json'] ?? '' ),
			! empty( $record['checked_in'] ) ? 'yes' : 'no',
			(string) ( $record['created_utc'] ?? '' ),
		];
	}

	/**
	 * Format custom fields.
	 *
	 * @param mixed $json Stored JSON.
	 */
	private static function fields( $json ): string {
		$fields = json_decode( (string) $json, true );

		if ( ! is_array( $fields ) ) {
			return '';
		}

		$parts = [];

		foreach ( $fields as $key => $value ) {
			if ( is_scalar( $value ) ) {
				$parts[] = $key . '=' . $value;
			}
		}

		return implode( '; ', $parts );
	}

	/**
	 * Prevent spreadsheet formula injection.
	 *
	 * @param string $value Cell value.
	 */
	public static function cell( string $value ): string {
		return Csv::cell( $value );
	}
}

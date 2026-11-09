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
		return [ 'Booking', 'Reference', 'Status', 'Event', 'Ticket', 'Name', 'Email', 'Checked in', 'Created' ];
	}

	/**
	 * Build rows for an event.
	 *
	 * @param int $event_id Event ID, or 0 for all.
	 * @return array<int, string[]>
	 */
	public static function rows( int $event_id ): array {
		$records = ( new Booking_Repository() )->attendees_for_event( $event_id );
		$rows    = [];

		foreach ( $records as $record ) {
			$rows[] = [
				(string) ( $record['booking_id'] ?? '' ),
				(string) ( $record['reference'] ?? '' ),
				(string) ( $record['booking_status'] ?? '' ),
				(string) get_the_title( (int) ( $record['event_id'] ?? 0 ) ),
				(string) ( $record['ticket_name'] ?? '' ),
				(string) ( $record['name'] ?? '' ),
				(string) ( $record['email'] ?? '' ),
				! empty( $record['checked_in'] ) ? 'yes' : 'no',
				(string) ( $record['created_utc'] ?? '' ),
			];
		}

		return $rows;
	}

	/**
	 * Prevent spreadsheet formula injection.
	 *
	 * @param string $value Cell value.
	 */
	public static function cell( string $value ): string {
		return '' !== $value && in_array( $value[0], [ '=', '+', '-', '@', "\t", "\r" ], true ) ? "'" . $value : $value;
	}
}

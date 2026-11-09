<?php
/**
 * Attendee CSV export.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\CLI;

use ReEvents\Data\Booking_Repository;

/**
 * Exports attendees as CSV.
 */
final class Export_Attendees {

	/**
	 * Export attendees.
	 *
	 * ## OPTIONS
	 *
	 * [--event=<id>]
	 * : Limit to one event.
	 *
	 * ## EXAMPLES
	 *
	 *     wp re-events export-attendees --event=68 > attendees.csv
	 *
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public static function run( array $assoc_args ): void {
		$event_id = isset( $assoc_args['event'] ) ? absint( $assoc_args['event'] ) : 0;
		$rows     = ( new Booking_Repository() )->attendees_for_event( $event_id );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv -- CLI CSV output.
		fputcsv( STDOUT, [ 'Booking', 'Reference', 'Status', 'Event', 'Ticket', 'Name', 'Email', 'Checked in', 'Created' ] );

		foreach ( $rows as $row ) {
			$line = [
				(string) ( $row['booking_id'] ?? '' ),
				(string) ( $row['reference'] ?? '' ),
				(string) ( $row['booking_status'] ?? '' ),
				(string) get_the_title( (int) ( $row['event_id'] ?? 0 ) ),
				(string) ( $row['ticket_name'] ?? '' ),
				(string) ( $row['name'] ?? '' ),
				(string) ( $row['email'] ?? '' ),
				! empty( $row['checked_in'] ) ? 'yes' : 'no',
				(string) ( $row['created_utc'] ?? '' ),
			];

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv -- CLI CSV output.
			fputcsv( STDOUT, array_map( [ self::class, 'cell' ], $line ) );
		}
	}

	/**
	 * Prevent spreadsheet formula injection.
	 *
	 * @param string $value Cell value.
	 */
	private static function cell( string $value ): string {
		return '' !== $value && in_array( $value[0], [ '=', '+', '-', '@', "\t", "\r" ], true ) ? "'" . $value : $value;
	}
}

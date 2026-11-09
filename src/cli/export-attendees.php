<?php
/**
 * Attendee CSV export.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\CLI;

use ReEvents\Services\Attendee_Export;

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

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv -- CLI CSV output.
		fputcsv( STDOUT, Attendee_Export::header() );

		foreach ( Attendee_Export::rows( $event_id ) as $row ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv -- CLI CSV output.
			fputcsv( STDOUT, array_map( [ Attendee_Export::class, 'cell' ], $row ) );
		}
	}
}

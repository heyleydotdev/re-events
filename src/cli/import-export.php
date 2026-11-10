<?php
/**
 * Import, export and occurrence CLI commands.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\CLI;

use ReEvents\Services\Csv;

/**
 * Handles `wp re-events import`, `export` and `expand-occurrences`.
 */
final class Import_Export {

	/**
	 * Export rows as CSV.
	 *
	 * ## OPTIONS
	 *
	 * <type>
	 * : events, venues or bookings.
	 *
	 * [--file=<path>]
	 * : Write to a file instead of standard output.
	 *
	 * ## EXAMPLES
	 *
	 *     wp re-events export events --file=events.csv
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public static function export( array $args, array $assoc_args ): void {
		$type   = (string) ( $args[0] ?? '' );
		$header = Csv::header( $type );

		if ( ! $header ) {
			\WP_CLI::error( __( 'Unknown export type. Use events, venues or bookings.', 're-events' ) );
		}

		$path   = (string) ( $assoc_args['file'] ?? '' );
		$handle = '' !== $path ? fopen( $path, 'w' ) : STDOUT; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- CLI export.

		if ( false === $handle ) {
			\WP_CLI::error( __( 'The export file could not be opened.', 're-events' ) );
		}

		fputcsv( $handle, $header, ',', '"', '\\' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv -- CLI CSV output.

		foreach ( Csv::rows( $type ) as $row ) {
			fputcsv( $handle, $row, ',', '"', '\\' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv -- CLI CSV output.
		}

		if ( '' !== $path ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- CLI export.
			fclose( $handle );
			\WP_CLI::success( sprintf( /* translators: %s: file path. */ __( 'Exported to %s.', 're-events' ), $path ) );
		}
	}

	/**
	 * Import a CSV file.
	 *
	 * ## OPTIONS
	 *
	 * <type>
	 * : events or venues.
	 *
	 * <file>
	 * : Path to the CSV file.
	 *
	 * ## EXAMPLES
	 *
	 *     wp re-events import events events.csv
	 *
	 * @param array<int, string> $args Positional arguments.
	 */
	public static function import( array $args ): void {
		$type   = (string) ( $args[0] ?? '' );
		$file   = (string) ( $args[1] ?? '' );
		$result = Csv::import( $type, $file );

		foreach ( $result['errors'] as $error ) {
			\WP_CLI::line( 'Warning: ' . $error );
		}

		\WP_CLI::success(
			sprintf(
				/* translators: 1: created count, 2: updated count. */
				__( 'Imported %1$d new and updated %2$d existing records.', 're-events' ),
				$result['created'],
				$result['updated']
			)
		);
	}

	/**
	 * Expand occurrences for all events.
	 *
	 * ## OPTIONS
	 *
	 * [--event=<id>]
	 * : Limit to one event.
	 *
	 * ## EXAMPLES
	 *
	 *     wp re-events expand-occurrences --event=68
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public static function expand_occurrences( array $args, array $assoc_args ): void {
		$event_id = isset( $assoc_args['event'] ) ? absint( $assoc_args['event'] ) : 0;

		if ( $event_id ) {
			$ids = [ $event_id ];
		} else {
			$ids = get_posts(
				[
					'post_type'      => 'reevents_event',
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				]
			);
		}

		$expanded = 0;

		foreach ( $ids as $id ) {
			if ( \ReEvents\Services\Occurrence_Service::sync( (int) $id ) ) {
				++$expanded;
			}
		}

		\WP_CLI::success(
			sprintf(
				/* translators: %d: event count. */
				__( 'Expanded occurrences for %d events.', 're-events' ),
				$expanded
			)
		);
	}
}

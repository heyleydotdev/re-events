<?php
/**
 * Booking concurrency test.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\CLI;

use ReEvents\Data\Ticket_Repository;
use WP_CLI;

/**
 * Fires parallel booking processes against one ticket.
 */
final class Concurrency_Test {

	/**
	 * Run the concurrency test.
	 *
	 * ## OPTIONS
	 *
	 * [--capacity=<count>]
	 * : Ticket capacity. Default 5.
	 *
	 * [--requests=<count>]
	 * : Parallel booking processes. Default 10.
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Arguments.
	 */
	public static function run( array $args, array $assoc_args ): void {
		$capacity = max( 1, (int) ( $assoc_args['capacity'] ?? 5 ) );
		$requests = max( 2, (int) ( $assoc_args['requests'] ?? 10 ) );
		$script   = RE_EVENTS_DIR . 'tests/concurrency/book.php';

		if ( ! is_readable( $script ) ) {
			WP_CLI::error( 'Concurrency helper not found. Run from the plugin source tree.' );
		}

		$event  = Test_Factory::event( [ 'title' => 'Concurrency test' ] );
		$ticket = Test_Factory::ticket( $event, [ 'capacity' => $capacity ] );

		$processes = [];

		for ( $i = 0; $i < $requests; $i++ ) {
			$descriptors = [
				1 => [ 'pipe', 'w' ],
				2 => [ 'pipe', 'w' ],
			];
			$environment = getenv();
			$env         = array_merge(
				$environment,
				[
					'REEVENTS_WP_ROOT' => ABSPATH,
					'REEVENTS_EVENT'   => (string) $event,
					'REEVENTS_TICKET'  => (string) $ticket,
				]
			);
			$command     = [ PHP_BINARY ];

			foreach ( [ 'mysqli.default_port', 'mysqli.default_socket' ] as $ini_key ) {
				$ini_value = ini_get( $ini_key );

				if ( is_string( $ini_value ) && '' !== $ini_value ) {
					$command[] = '-d';
					$command[] = $ini_key . '=' . $ini_value;
				}
			}

			$command[] = $script;

			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open -- Test process.
			$process = proc_open( $command, $descriptors, $pipes, null, $env );

			if ( is_resource( $process ) ) {
				$processes[] = [ $process, $pipes ];
			}
		}

		$booked = 0;
		$sold   = 0;
		$errors = [];

		foreach ( $processes as [ $process, $pipes ] ) {
			$output = (string) stream_get_contents( $pipes[1] );
			$error  = (string) stream_get_contents( $pipes[2] );

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Test process pipes.
			fclose( $pipes[1] );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Test process pipes.
			fclose( $pipes[2] );
			proc_close( $process );

			if ( str_contains( $output, 'ok:' ) ) {
				++$booked;
			} elseif ( str_contains( $output, 'reevents_booking_sold_out' ) ) {
				++$sold;
			} else {
				$errors[] = trim( $output . ' ' . $error );
			}
		}

		$stored = (int) ( ( new Ticket_Repository() )->find( $ticket )['sold'] ?? 0 );

		self::cleanup( $event, $ticket );

		if ( $errors ) {
			WP_CLI::error( 'Concurrent booking errors: ' . implode( ' | ', $errors ) );
		}

		if ( $booked !== $capacity || $sold !== $requests - $capacity || $stored !== $capacity ) {
			WP_CLI::error(
				sprintf(
					'Capacity mismatch: booked %d, sold out %d, stored %d (capacity %d, requests %d).',
					$booked,
					$sold,
					$stored,
					$capacity,
					$requests
				)
			);
		}

		WP_CLI::success( sprintf( 'Capacity held: %d booked, %d rejected.', $booked, $sold ) );
	}

	/**
	 * Remove test fixtures.
	 *
	 * @param int $event  Event ID.
	 * @param int $ticket Ticket ID.
	 */
	private static function cleanup( int $event, int $ticket ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Test cleanup.
		$bookings = $wpdb->get_col(
			$wpdb->prepare( "SELECT id FROM {$wpdb->prefix}reevents_bookings WHERE event_id = %d", $event )
		);

		if ( $bookings ) {
			$ids = implode( ',', array_map( 'intval', $bookings ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Test cleanup.
			$wpdb->query( "DELETE FROM {$wpdb->prefix}reevents_attendees WHERE booking_id IN ({$ids})" );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Test cleanup.
			$wpdb->query( "DELETE FROM {$wpdb->prefix}reevents_bookings WHERE id IN ({$ids})" );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Test cleanup.
		$wpdb->delete( $wpdb->prefix . 'reevents_tickets', [ 'id' => $ticket ] );

		wp_delete_post( $event, true );
	}
}

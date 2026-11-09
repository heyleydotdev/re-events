<?php
/**
 * One parallel booking process.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

$reevents_root = getenv( 'REEVENTS_WP_ROOT' );

if ( ! is_string( $reevents_root ) || ! is_readable( $reevents_root . 'wp-load.php' ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI output.
	fwrite( STDERR, 'WordPress root is not readable.' );
	exit( 2 );
}

require $reevents_root . 'wp-load.php';

$reevents_event  = (int) getenv( 'REEVENTS_EVENT' );
$reevents_ticket = (int) getenv( 'REEVENTS_TICKET' );

$reevents_result = \ReEvents\Services\Booking_Service::create(
	[
		'event_id'  => $reevents_event,
		'ticket_id' => $reevents_ticket,
		'quantity'  => 1,
	]
);

if ( is_wp_error( $reevents_result ) ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.
	echo 'error:' . $reevents_result->get_error_code();
	exit( 0 );
}

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.
echo 'ok:' . (int) $reevents_result;

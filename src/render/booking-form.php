<?php
/**
 * Booking form renderer.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Render;

use ReEvents\Data\Ticket_Repository;

/**
 * Renders the booking form.
 */
final class Booking_Form {

	/**
	 * Render the booking form.
	 *
	 * @param array<string, mixed> $args Widget arguments.
	 */
	public static function render( array $args ): string {
		$event_id = (int) ( $args['event'] ?? 0 );

		if ( ! $event_id ) {
			$event_id = (int) get_the_ID();
		}

		if ( 'reevents_event' !== get_post_type( $event_id ) || 'publish' !== get_post_status( $event_id ) ) {
			return '';
		}

		$tickets = ( new Ticket_Repository() )->for_event( $event_id, 'active' );

		if ( ! $tickets ) {
			return '<p class="reevents-empty">' . esc_html__( 'No tickets are available.', 're-events' ) . '</p>';
		}

		wp_enqueue_script(
			'reevents-booking',
			plugins_url( 'assets/js/frontend-booking.js', RE_EVENTS_FILE ),
			[],
			RE_EVENTS_VERSION,
			true
		);

		return Template::render(
			'booking-form',
			[
				'event_id' => $event_id,
				'tickets'  => $tickets,
				'state'    => self::state(),
				'action'   => admin_url( 'admin-post.php' ),
				'endpoint' => rest_url( 're-events/v1/events/' . $event_id . '/bookings' ),
				'args'     => $args,
			]
		);
	}

	/**
	 * Read the redirect state.
	 *
	 * @return array{status:string,reference:string,message:string}
	 */
	private static function state(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only result flags.
		$status    = isset( $_GET['reevents_booking'] ) ? sanitize_key( wp_unslash( (string) $_GET['reevents_booking'] ) ) : '';
		$reference = isset( $_GET['reference'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['reference'] ) ) : '';
		$code      = isset( $_GET['code'] ) ? sanitize_key( wp_unslash( (string) $_GET['code'] ) ) : '';
		// phpcs:enable

		return [
			'status'    => in_array( $status, [ 'success', 'error' ], true ) ? $status : '',
			'reference' => 'success' === $status ? $reference : '',
			'message'   => 'error' === $status ? self::message( $code ) : '',
		];
	}

	/**
	 * Map an error code to a message.
	 *
	 * @param string $code Error code.
	 */
	private static function message( string $code ): string {
		$messages = [
			'reevents_booking_sold_out'         => __( 'Not enough seats are available.', 're-events' ),
			'reevents_booking_sale_not_started' => __( 'Ticket sales have not started.', 're-events' ),
			'reevents_booking_sale_ended'       => __( 'Ticket sales have ended.', 're-events' ),
			'reevents_booking_min_quantity'     => __( 'Too few seats selected.', 're-events' ),
			'reevents_booking_max_quantity'     => __( 'Too many seats selected.', 're-events' ),
			'reevents_booking_rate_limited'     => __( 'Too many attempts. Try again later.', 're-events' ),
			'reevents_rate_limited'             => __( 'Too many attempts. Try again later.', 're-events' ),
		];

		return $messages[ $code ] ?? __( 'The booking could not be created. Please try again.', 're-events' );
	}
}

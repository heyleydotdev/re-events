<?php
/**
 * Booking form renderer.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Render;

use ReEvents\Admin\Settings;
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

		if ( 'cancelled' === (string) get_post_meta( $event_id, '_reevents_status', true ) ) {
			return '<p class="reevents-empty">' . esc_html__( 'This event has been cancelled.', 're-events' ) . '</p>';
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
				'fields'   => Settings::attendee_fields(),
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
		$token = isset( $_GET['reevents_booking_token'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['reevents_booking_token'] ) ) : '';
		$code  = isset( $_GET['code'] ) ? sanitize_key( wp_unslash( (string) $_GET['code'] ) ) : '';
		// phpcs:enable

		if ( '' !== $token ) {
			$result = get_transient( 'reevents_booking_result_' . $token );

			if ( is_array( $result ) ) {
				delete_transient( 'reevents_booking_result_' . $token );

				return [
					'status'    => 'success',
					'reference' => (string) ( $result['reference'] ?? '' ),
					'message'   => '',
				];
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only result flag.
		$error = isset( $_GET['reevents_booking'] ) && 'error' === sanitize_key( wp_unslash( (string) $_GET['reevents_booking'] ) );

		return [
			'status'    => $error ? 'error' : '',
			'reference' => '',
			'message'   => $error ? self::message( $code ) : '',
		];
	}

	/**
	 * Map an error code to a message.
	 *
	 * @param string $code Error code.
	 */
	private static function message( string $code ): string {
		$messages = [
			'reevents_booking_sold_out'           => __( 'Not enough seats are available.', 're-events' ),
			'reevents_booking_sale_not_started'   => __( 'Ticket sales have not started.', 're-events' ),
			'reevents_booking_sale_ended'         => __( 'Ticket sales have ended.', 're-events' ),
			'reevents_booking_min_quantity'       => __( 'Too few seats selected.', 're-events' ),
			'reevents_booking_max_quantity'       => __( 'Too many seats selected.', 're-events' ),
			'reevents_booking_rate_limited'       => __( 'Too many attempts. Try again later.', 're-events' ),
			'reevents_rate_limited'               => __( 'Too many attempts. Try again later.', 're-events' ),
			'reevents_booking_event_cancelled'    => __( 'This event has been cancelled.', 're-events' ),
			'reevents_booking_invalid_occurrence' => __( 'The selected time is not available.', 're-events' ),
			'reevents_booking_too_many_attendees' => __( 'There are more attendees than seats.', 're-events' ),
			'invalid_attendee'                    => __( 'Enter a valid name and email address.', 're-events' ),
			'required_field'                      => __( 'Fill in all required attendee fields.', 're-events' ),
			'reevents_payment_cancelled'          => __( 'The payment was cancelled.', 're-events' ),
			'reevents_payment_pending'            => __( 'The payment is not complete yet.', 're-events' ),
			'reevents_payment_missing_key'        => __( 'The payment gateway is not configured.', 're-events' ),
		];

		return $messages[ $code ] ?? __( 'The booking could not be created. Please try again.', 're-events' );
	}
}

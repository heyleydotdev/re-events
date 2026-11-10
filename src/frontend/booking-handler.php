<?php
/**
 * Booking form handler.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Frontend;

use ReEvents\Admin\Settings;
use ReEvents\Data\Booking_Repository;
use ReEvents\Services\Booking_Service;
use ReEvents\Services\Payment_Service;
use ReEvents\Services\Rate_Limit;

/**
 * Handles the no-JS booking form.
 */
final class Booking_Handler {

	/**
	 * Hook into WordPress.
	 */
	public static function register(): void {
		add_action( 'admin_post_nopriv_reevents_book', [ self::class, 'handle' ] );
		add_action( 'admin_post_reevents_book', [ self::class, 'handle' ] );
	}

	/**
	 * Handle a booking submission.
	 */
	public static function handle(): void {
		$event_id = isset( $_POST['event_id'] ) ? absint( wp_unslash( $_POST['event_id'] ) ) : 0;
		$redirect = wp_get_referer();

		if ( ! $redirect ) {
			$redirect = $event_id ? (string) get_permalink( $event_id ) : home_url( '/' );
		}

		if ( ! $redirect ) {
			$redirect = home_url( '/' );
		}

		if ( ! isset( $_POST['reevents_book_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['reevents_book_nonce'] ) ), 'reevents_book' ) ) {
			self::redirect( $redirect, 'error', 'invalid' );
		}

		if ( ! empty( $_POST['website'] ) ) {
			self::redirect( $redirect, 'error', 'invalid' );
		}

		if ( Rate_Limit::hit( 'booking_form' ) ) {
			self::redirect( $redirect, 'error', 'reevents_booking_rate_limited' );
		}

		$fields = [];

		if ( isset( $_POST['reevents_fields'] ) && is_array( $_POST['reevents_fields'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below.
			foreach ( wp_unslash( $_POST['reevents_fields'] ) as $field_key => $field_value ) {
				if ( is_scalar( $field_value ) ) {
					$fields[ sanitize_key( (string) $field_key ) ] = sanitize_text_field( (string) $field_value );
				}
			}
		}

		$name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

		if ( '' === $name || ! is_email( $email ) ) {
			self::redirect( $redirect, 'error', 'invalid_attendee' );
		}

		foreach ( Settings::attendee_fields() as $field_key => $field ) {
			if ( ! empty( $field['required'] ) && '' === trim( (string) ( $fields[ $field_key ] ?? '' ) ) ) {
				self::redirect( $redirect, 'error', 'required_field' );
			}
		}

		$result = Booking_Service::create(
			[
				'event_id'  => $event_id,
				'ticket_id' => isset( $_POST['ticket_id'] ) ? absint( wp_unslash( $_POST['ticket_id'] ) ) : 0,
				'quantity'  => isset( $_POST['quantity'] ) ? absint( wp_unslash( $_POST['quantity'] ) ) : 1,
				'attendees' => [
					[
						'name'   => $name,
						'email'  => $email,
						'fields' => $fields,
					],
				],
			]
		);

		if ( is_wp_error( $result ) ) {
			self::redirect( $redirect, 'error', $result->get_error_code() );
		}

		$checkout = Payment_Service::checkout( (int) $result );

		if ( is_wp_error( $checkout ) ) {
			Booking_Service::cancel( (int) $result );
			self::redirect( $redirect, 'error', $checkout->get_error_code() );
		}

		if ( '' !== $checkout ) {
			wp_safe_redirect( $checkout );
			exit;
		}

		$booking = ( new Booking_Repository() )->find( (int) $result );
		self::redirect( $redirect, 'success', (string) ( $booking['reference'] ?? '' ) );
	}

	/**
	 * Redirect back with a result flag.
	 *
	 * @param string $url       Redirect URL.
	 * @param string $status    success or error.
	 * @param string $value     Reference or error code.
	 */
	public static function redirect( string $url, string $status, string $value ): void {
		if ( 'success' === $status ) {
			$token = wp_generate_password( 32, false, false );
			set_transient( 'reevents_booking_result_' . $token, [ 'reference' => $value ], 5 * MINUTE_IN_SECONDS );
			$args = [ 'reevents_booking_token' => $token ];
		} else {
			$args = [
				'reevents_booking' => 'error',
				'code'             => $value,
			];
		}

		wp_safe_redirect( add_query_arg( $args, $url ) );
		exit;
	}
}

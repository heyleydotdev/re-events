<?php
/**
 * Payment return handler.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Frontend;

use ReEvents\Admin\Settings;
use ReEvents\Data\Booking_Repository;
use ReEvents\Services\Booking_Service;
use ReEvents\Services\Payment_Service;

/**
 * Handles the provider success and cancel return URLs.
 */
final class Payment_Return {

	/**
	 * Hook into WordPress.
	 */
	public static function register(): void {
		add_action( 'template_redirect', [ self::class, 'handle' ] );
	}

	/**
	 * Sync the payment and redirect to the event.
	 */
	public static function handle(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Provider return URL.
		$result     = isset( $_GET['reevents_payment'] ) ? sanitize_key( wp_unslash( (string) $_GET['reevents_payment'] ) ) : '';
		$booking_id = isset( $_GET['booking'] ) ? absint( wp_unslash( (string) $_GET['booking'] ) ) : 0;
		$token      = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['token'] ) ) : '';
		// phpcs:enable

		if ( ! in_array( $result, [ 'success', 'cancel' ], true ) || $booking_id <= 0 || '' === $token ) {
			return;
		}

		$repo    = new Booking_Repository();
		$booking = $repo->find( $booking_id );

		if ( ! $booking || ! hash_equals( (string) $booking['reference'], $token ) ) {
			return;
		}

		$url = (string) get_permalink( (int) $booking['event_id'] );

		if ( '' === $url ) {
			$url = home_url( '/' );
		}

		if ( 'cancel' === $result ) {
			$sync    = Payment_Service::sync_booking( $booking_id, true );
			$booking = $repo->find( $booking_id );

			if ( is_wp_error( $sync ) && ! Payment_Service::has_successful_payment( $booking_id ) ) {
				Booking_Handler::redirect( $url, 'error', 'reevents_payment_pending' );
			}

			if ( $booking && 'confirmed' === (string) $booking['status'] ) {
				Booking_Handler::redirect( $url, 'success', (string) $booking['reference'] );
			}

			if ( $booking && 'pending' === (string) $booking['status'] && Payment_Service::has_successful_payment( $booking_id ) ) {
				Booking_Handler::redirect( $url, 'error', 'reevents_booking_awaiting_approval' );
			}

			if ( $booking && 'pending' === (string) $booking['status'] ) {
				Payment_Service::cancel_pending( $booking_id );
				Booking_Service::cancel( $booking_id );
			}

			Booking_Handler::redirect( $url, 'error', 'reevents_payment_cancelled' );
		}

		Payment_Service::sync_booking( $booking_id );

		$booking = $repo->find( $booking_id );

		if ( $booking && 'confirmed' === (string) $booking['status'] ) {
			Booking_Handler::redirect( $url, 'success', (string) $booking['reference'] );
		}

		if ( $booking && 'pending' === (string) $booking['status'] && Settings::get( 'require_approval', false ) ) {
			Booking_Handler::redirect( $url, 'error', 'reevents_booking_awaiting_approval' );
		}

		Booking_Handler::redirect( $url, 'error', 'reevents_payment_pending' );
	}
}

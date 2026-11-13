<?php
/**
 * Booking action handler.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Frontend;

use ReEvents\Data\Booking_Repository;
use ReEvents\Services\Booking_Access;
use ReEvents\Services\Booking_Self_Service;
use WP_Error;

/**
 * Handles visitor cancel and reactivate forms.
 */
final class Booking_Actions {

	/**
	 * Hook into WordPress.
	 */
	public static function register(): void {
		foreach ( [ 'reevents_booking_cancel', 'reevents_booking_reactivate' ] as $action ) {
			add_action( 'admin_post_' . $action, [ self::class, 'handle' ] );
			add_action( 'admin_post_nopriv_' . $action, [ self::class, 'handle' ] );
		}
	}

	/**
	 * Run a self-service action and redirect.
	 */
	public static function handle(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in process().
		$decision = self::process( (array) wp_unslash( $_POST ) );

		self::redirect( $decision['redirect'], $decision['result'], $decision['reason'] );
	}

	/**
	 * Decide what a form submission should do.
	 *
	 * @param array<string, mixed> $input Submitted fields.
	 * @return array{redirect:string,result:string,reason:string}
	 */
	public static function process( array $input ): array {
		$redirect    = (string) wp_validate_redirect(
			esc_url_raw( (string) ( $input['redirect_to'] ?? '' ) ),
			home_url( '/' )
		);
		$action      = sanitize_key( (string) ( $input['action'] ?? '' ) );
		$booking_id  = absint( $input['booking_id'] ?? 0 );
		$nonce       = sanitize_text_field( (string) ( $input['reevents_booking_nonce'] ?? '' ) );
		$credentials = [
			'key'       => sanitize_text_field( (string) ( $input['key'] ?? '' ) ),
			'reference' => sanitize_text_field( (string) ( $input['reference'] ?? '' ) ),
			'email'     => sanitize_email( (string) ( $input['email'] ?? '' ) ),
		];

		$failure = static function ( string $reason ) use ( $redirect ): array {
			return [
				'redirect' => $redirect,
				'result'   => 'error',
				'reason'   => $reason,
			];
		};

		if ( ! in_array( $action, [ 'reevents_booking_cancel', 'reevents_booking_reactivate' ], true ) || $booking_id <= 0 ) {
			return $failure( 'status' );
		}

		if ( ! wp_verify_nonce( $nonce, 'reevents_booking_action_' . $booking_id ) ) {
			return $failure( 'expired' );
		}

		if ( ! Booking_Self_Service::enabled() ) {
			return $failure( 'disabled' );
		}

		$booking = ( new Booking_Repository() )->find( $booking_id );

		if ( ! $booking || ! Booking_Access::authorize( $booking, $credentials ) ) {
			return $failure( 'status' );
		}

		$result = 'reevents_booking_cancel' === $action
			? Booking_Self_Service::cancel( $booking_id )
			: Booking_Self_Service::reactivate( $booking_id );

		if ( is_wp_error( $result ) ) {
			return $failure( self::error_reason( $result ) );
		}

		return [
			'redirect' => $redirect,
			'result'   => 'reevents_booking_cancel' === $action ? 'cancelled' : 'reactivated',
			'reason'   => '',
		];
	}

	/**
	 * Reason code from an error.
	 *
	 * @param WP_Error $error Error.
	 */
	private static function error_reason( WP_Error $error ): string {
		$data = (array) $error->get_error_data();

		return isset( $data['reason'] ) ? sanitize_key( (string) $data['reason'] ) : 'status';
	}

	/**
	 * Redirect back to the bookings page.
	 *
	 * @param string $url    Target URL.
	 * @param string $result Result code.
	 * @param string $reason Reason code.
	 */
	private static function redirect( string $url, string $result, string $reason = '' ): void {
		$args = [ 'reevents_booking_result' => $result ];

		if ( '' !== $reason ) {
			$args['reevents_booking_reason'] = $reason;
		}

		wp_safe_redirect( add_query_arg( $args, $url ) );
		exit;
	}
}

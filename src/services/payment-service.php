<?php
/**
 * Payment service.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Services;

use ReEvents\Admin\Settings;
use ReEvents\Data\Booking_Repository;
use ReEvents\Data\Payment_Repository;
use ReEvents\Services\Gateways\Gateway_Registry;
use WP_Error;

/**
 * Coordinates bookings with payment gateways.
 */
final class Payment_Service {

	/**
	 * Hook into WordPress.
	 */
	public static function register(): void {
		add_action( 'init', [ Gateway_Registry::class, 'register_defaults' ], 1 );
	}

	/**
	 * Start a payment for a booking.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $gateway_id Gateway ID.
	 * @return array{payment_id:int,gateway_ref:string,status:string,redirect_url:string}|WP_Error
	 */
	public static function start( int $booking_id, string $gateway_id ) {
		$bookings = new Booking_Repository();
		$booking  = $bookings->find( $booking_id );

		if ( ! $booking ) {
			return new WP_Error( 'reevents_payment_invalid_booking', __( 'Invalid booking.', 're-events' ), [ 'status' => 404 ] );
		}

		if ( (int) $booking['total_minor'] <= 0 ) {
			return new WP_Error( 'reevents_payment_free_booking', __( 'This booking does not need payment.', 're-events' ), [ 'status' => 400 ] );
		}

		$gateway = Gateway_Registry::get( $gateway_id );

		if ( ! $gateway ) {
			return new WP_Error( 'reevents_payment_invalid_gateway', __( 'Invalid payment gateway.', 're-events' ), [ 'status' => 400 ] );
		}

		$repo       = new Payment_Repository();
		$payment_id = $repo->create(
			[
				'booking_id'   => $booking_id,
				'gateway'      => $gateway_id,
				'status'       => 'pending',
				'amount_minor' => (int) $booking['total_minor'],
				'currency'     => (string) $booking['currency'],
			]
		);

		if ( is_wp_error( $payment_id ) ) {
			return $payment_id;
		}

		$payment = $repo->find( $payment_id );
		$result  = $gateway->create_payment( $payment ?? [], $booking );

		if ( is_wp_error( $result ) ) {
			$repo->update(
				$payment_id,
				[
					'status' => 'failed',
					'data'   => [ 'error' => $result->get_error_message() ],
				]
			);

			return $result;
		}

		$repo->update(
			$payment_id,
			[
				'gateway_ref' => $result['gateway_ref'],
				'status'      => $result['status'],
				'data'        => $result['data'],
			]
		);
		$bookings->set_gateway( $booking_id, $gateway_id, $result['gateway_ref'] );

		return [
			'payment_id'   => $payment_id,
			'gateway_ref'  => $result['gateway_ref'],
			'status'       => $result['status'],
			'redirect_url' => $result['redirect_url'],
		];
	}

	/**
	 * Start or reuse checkout for a booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return string|WP_Error Redirect URL, or an empty string when no payment is needed.
	 */
	public static function checkout( int $booking_id ) {
		$gateway_id = (string) Settings::get( 'payment_gateway', 'none' );

		if ( 'none' === $gateway_id ) {
			return '';
		}

		$booking = ( new Booking_Repository() )->find( $booking_id );

		if ( ! $booking || (int) $booking['total_minor'] <= 0 ) {
			return '';
		}

		if ( 'pending' !== (string) $booking['status'] ) {
			return '';
		}

		$repo = new Payment_Repository();

		foreach ( $repo->for_booking( $booking_id ) as $payment ) {
			$redirect = (string) ( $payment['data']['redirect_url'] ?? '' );

			if ( 'pending' === $payment['status'] && '' !== $redirect ) {
				return $redirect;
			}
		}

		$result = self::start( $booking_id, $gateway_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $result['redirect_url'];
	}

	/**
	 * Sync the latest payment of a booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return bool|WP_Error
	 */
	public static function sync_booking( int $booking_id ) {
		$payments = ( new Payment_Repository() )->for_booking( $booking_id );

		if ( ! $payments ) {
			return new WP_Error( 'reevents_payment_not_found', __( 'Payment not found.', 're-events' ), [ 'status' => 404 ] );
		}

		$payment = end( $payments );

		return self::sync( (int) $payment['id'] );
	}

	/**
	 * Handle a gateway webhook.
	 *
	 * @param string               $gateway_id Gateway ID.
	 * @param string               $payload    Raw body.
	 * @param array<string, mixed> $headers    Headers.
	 * @return bool|WP_Error
	 */
	public static function handle_webhook( string $gateway_id, string $payload, array $headers ) {
		$gateway = Gateway_Registry::get( $gateway_id );

		if ( ! $gateway ) {
			return new WP_Error( 'reevents_payment_invalid_gateway', __( 'Invalid payment gateway.', 're-events' ), [ 'status' => 404 ] );
		}

		$verified = $gateway->verify_webhook( $payload, $headers );

		if ( is_wp_error( $verified ) ) {
			return $verified;
		}

		$repo    = new Payment_Repository();
		$payment = $repo->find_by_ref( $gateway_id, $verified['gateway_ref'] );

		if ( ! $payment ) {
			return new WP_Error( 'reevents_payment_not_found', __( 'Payment not found.', 're-events' ), [ 'status' => 404 ] );
		}

		if ( ! in_array( $verified['status'], [ 'pending', 'succeeded', 'failed', 'refunded' ], true ) ) {
			return true;
		}

		$repo->update( (int) $payment['id'], [ 'status' => $verified['status'] ] );

		if ( 'succeeded' === $verified['status'] ) {
			Booking_Service::update_status( (int) $payment['booking_id'], 'confirmed' );
		} elseif ( 'refunded' === $verified['status'] ) {
			Booking_Service::cancel( (int) $payment['booking_id'] );
		}

		return true;
	}

	/**
	 * Refund a payment.
	 *
	 * @param int $payment_id   Payment ID.
	 * @param int $amount_minor Amount in the smallest currency unit, or 0 for full.
	 * @return bool|WP_Error
	 */
	public static function refund( int $payment_id, int $amount_minor = 0 ) {
		$repo    = new Payment_Repository();
		$payment = $repo->find( $payment_id );

		if ( ! $payment ) {
			return new WP_Error( 'reevents_payment_not_found', __( 'Payment not found.', 're-events' ), [ 'status' => 404 ] );
		}

		$gateway = Gateway_Registry::get( (string) $payment['gateway'] );

		if ( ! $gateway ) {
			return new WP_Error( 'reevents_payment_invalid_gateway', __( 'Invalid payment gateway.', 're-events' ), [ 'status' => 400 ] );
		}

		$result = $gateway->refund( (string) $payment['gateway_ref'], $amount_minor );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( ! $result ) {
			return new WP_Error( 'reevents_payment_refund_failed', __( 'The refund could not be created.', 're-events' ), [ 'status' => 500 ] );
		}

		$repo->update( $payment_id, [ 'status' => 'refunded' ] );

		if ( $amount_minor <= 0 || $amount_minor >= (int) $payment['amount_minor'] ) {
			Booking_Service::cancel( (int) $payment['booking_id'] );
		}

		return true;
	}

	/**
	 * Sync a payment status.
	 *
	 * @param int $payment_id Payment ID.
	 * @return bool|WP_Error
	 */
	public static function sync( int $payment_id ) {
		$repo    = new Payment_Repository();
		$payment = $repo->find( $payment_id );

		if ( ! $payment ) {
			return new WP_Error( 'reevents_payment_not_found', __( 'Payment not found.', 're-events' ), [ 'status' => 404 ] );
		}

		$gateway = Gateway_Registry::get( (string) $payment['gateway'] );

		if ( ! $gateway ) {
			return new WP_Error( 'reevents_payment_invalid_gateway', __( 'Invalid payment gateway.', 're-events' ), [ 'status' => 400 ] );
		}

		$result = $gateway->sync( (string) $payment['gateway_ref'] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$repo->update( $payment_id, [ 'status' => $result['status'] ] );

		if ( 'succeeded' === $result['status'] ) {
			Booking_Service::update_status( (int) $payment['booking_id'], 'confirmed' );
		}

		return true;
	}
}

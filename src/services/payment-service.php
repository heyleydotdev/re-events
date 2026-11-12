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

		$updated = $repo->update(
			$payment_id,
			[
				'gateway_ref' => $result['gateway_ref'],
				'status'      => $result['status'],
				'data'        => $result['data'],
			]
		);

		if ( ! $updated ) {
			return new WP_Error( 'reevents_payment_update_failed', __( 'The payment could not be updated.', 're-events' ), [ 'status' => 500 ] );
		}

		$bookings->set_gateway( $booking_id, $gateway_id, $result['gateway_ref'] );

		if ( 'succeeded' === $result['status'] ) {
			self::confirm_booking( $booking_id );
		}

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
	 * @param int  $booking_id Booking ID.
	 * @param bool $read_only   Read only; do not capture or complete the payment.
	 * @return bool|WP_Error
	 */
	public static function sync_booking( int $booking_id, bool $read_only = false ) {
		$payments = ( new Payment_Repository() )->for_booking( $booking_id );

		if ( ! $payments ) {
			return new WP_Error( 'reevents_payment_not_found', __( 'Payment not found.', 're-events' ), [ 'status' => 404 ] );
		}

		$payment = end( $payments );

		return self::sync( (int) $payment['id'], $read_only );
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
		$payment = null;

		if ( ! empty( $verified['payment_id'] ) ) {
			$payment = $repo->find( (int) $verified['payment_id'] );
		}

		if ( ! $payment && ! empty( $verified['payment_intent'] ) ) {
			$payment = $repo->find_by_intent( (string) $verified['payment_intent'] );
		}

		if ( ! $payment ) {
			$payment = $repo->find_by_ref( $gateway_id, (string) $verified['gateway_ref'] );
		}

		if ( ! $payment ) {
			return new WP_Error( 'reevents_payment_not_found', __( 'Payment not found.', 're-events' ), [ 'status' => 404 ] );
		}

		$original = is_array( $payment['data'] ?? null ) ? $payment['data'] : [];
		$data     = $original;

		if ( ! empty( $verified['payment_intent'] ) ) {
			$data['payment_intent'] = (string) $verified['payment_intent'];
		}

		if ( ! empty( $verified['refunded_minor'] ) ) {
			$already = (int) ( $data['refunded_minor'] ?? 0 );
			$amount  = (int) $verified['refunded_minor'];

			$data['refunded_minor'] = ! empty( $verified['refund_cumulative'] )
				? max( $already, $amount )
				: $already + $amount;
			$data['refunded_minor'] = min( (int) $payment['amount_minor'], (int) $data['refunded_minor'] );
		}

		$status = (string) $verified['status'];

		if ( 'refunded' === $status && (int) ( $data['refunded_minor'] ?? 0 ) < (int) $payment['amount_minor'] ) {
			$status = 'succeeded';
		}

		if ( $data !== $original ) {
			$repo->update( (int) $payment['id'], [ 'data' => $data ] );
			$payment['data'] = $data;
		}

		return self::apply_status( $payment, $status );
	}

	/**
	 * Refund a payment.
	 *
	 * @param int $payment_id   Payment ID.
	 * @param int $amount_minor Amount in the smallest currency unit, or 0 for full.
	 * @return bool|WP_Error
	 */
	public static function refund( int $payment_id, int $amount_minor = 0 ) {
		$repo = new Payment_Repository();

		if ( ! Lock::acquire( 'reevents_refund_lock_' . $payment_id ) ) {
			return new WP_Error( 'reevents_payment_refund_in_progress', __( 'A refund is already being processed.', 're-events' ), [ 'status' => 409 ] );
		}

		try {
			$payment = $repo->find( $payment_id );

			if ( ! $payment ) {
				return new WP_Error( 'reevents_payment_not_found', __( 'Payment not found.', 're-events' ), [ 'status' => 404 ] );
			}

			if ( 'refunded' === (string) $payment['status'] ) {
				return true;
			}

			$gateway = Gateway_Registry::get( (string) $payment['gateway'] );

			if ( ! $gateway ) {
				return new WP_Error( 'reevents_payment_invalid_gateway', __( 'Invalid payment gateway.', 're-events' ), [ 'status' => 400 ] );
			}

			$data      = is_array( $payment['data'] ?? null ) ? $payment['data'] : [];
			$already   = max( 0, (int) ( $data['refunded_minor'] ?? 0 ) );
			$total     = max( 0, (int) $payment['amount_minor'] );
			$remaining = max( 0, $total - $already );

			if ( 0 === $remaining ) {
				return true;
			}

			$amount_minor = max( 0, $amount_minor );
			$request      = $amount_minor > 0 ? min( $amount_minor, $remaining ) : $remaining;
			$result       = $gateway->refund( (string) $payment['gateway_ref'], $request );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			if ( ! $result ) {
				return new WP_Error( 'reevents_payment_refund_failed', __( 'The refund could not be created.', 're-events' ), [ 'status' => 500 ] );
			}

			$refunded = $already + $request;
			$full     = $refunded >= $total;

			$data['refunded_minor'] = $refunded;

			$updated = $repo->update(
				$payment_id,
				[
					'status' => $full ? 'refunded' : 'succeeded',
					'data'   => $data,
				]
			);

			if ( ! $updated ) {
				return new WP_Error( 'reevents_payment_update_failed', __( 'The payment could not be updated.', 're-events' ), [ 'status' => 500 ] );
			}

			if ( $full ) {
				Booking_Service::cancel( (int) $payment['booking_id'] );
			}

			return true;
		} finally {
			Lock::release( 'reevents_refund_lock_' . $payment_id );
		}
	}

	/**
	 * Refund the latest successful payment for a booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return bool|WP_Error
	 */
	public static function refund_booking( int $booking_id ) {
		$payments = ( new Payment_Repository() )->for_booking( $booking_id );
		$refunded = false;

		foreach ( $payments as $payment ) {
			if ( 'succeeded' !== (string) $payment['status'] ) {
				continue;
			}

			$result = self::refund( (int) $payment['id'] );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$refunded = true;
		}

		if ( $refunded ) {
			return true;
		}

		return new WP_Error( 'reevents_payment_not_refundable', __( 'No successful payment was found.', 're-events' ), [ 'status' => 400 ] );
	}

	/**
	 * Whether a booking has a successful payment.
	 *
	 * @param int $booking_id Booking ID.
	 */
	public static function has_successful_payment( int $booking_id ): bool {
		foreach ( ( new Payment_Repository() )->for_booking( $booking_id ) as $payment ) {
			if ( 'succeeded' === (string) $payment['status'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Sync a payment status.
	 *
	 * @param int  $payment_id Payment ID.
	 * @param bool $read_only   Read only; do not capture or complete the payment.
	 * @return bool|WP_Error
	 */
	public static function sync( int $payment_id, bool $read_only = false ) {
		$repo    = new Payment_Repository();
		$payment = $repo->find( $payment_id );

		if ( ! $payment ) {
			return new WP_Error( 'reevents_payment_not_found', __( 'Payment not found.', 're-events' ), [ 'status' => 404 ] );
		}

		$gateway = Gateway_Registry::get( (string) $payment['gateway'] );

		if ( ! $gateway ) {
			return new WP_Error( 'reevents_payment_invalid_gateway', __( 'Invalid payment gateway.', 're-events' ), [ 'status' => 400 ] );
		}

		$result = $gateway->sync( (string) $payment['gateway_ref'], $read_only );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return self::apply_status( $payment, (string) $result['status'] );
	}

	/**
	 * Apply a provider status to a payment.
	 *
	 * @param array<string, mixed> $payment Payment row.
	 * @param string               $status  New status.
	 * @return bool|WP_Error
	 */
	private static function apply_status( array $payment, string $status ) {
		if ( ! in_array( $status, [ 'pending', 'succeeded', 'failed', 'refunded' ], true ) ) {
			return true;
		}

		$current = (string) $payment['status'];

		if ( 'refunded' === $current && 'refunded' !== $status ) {
			return true;
		}

		if ( 'succeeded' === $current && 'refunded' !== $status ) {
			return true;
		}

		if ( $current === $status ) {
			return true;
		}

		$repo    = new Payment_Repository();
		$updated = $repo->update( (int) $payment['id'], [ 'status' => $status ] );

		if ( ! $updated ) {
			return new WP_Error( 'reevents_payment_update_failed', __( 'The payment could not be updated.', 're-events' ), [ 'status' => 500 ] );
		}

		if ( 'succeeded' === $status ) {
			self::confirm_booking( (int) $payment['booking_id'] );
		} elseif ( 'refunded' === $status ) {
			Booking_Service::cancel( (int) $payment['booking_id'] );
		}

		return true;
	}

	/**
	 * Confirm a booking after payment, unless approval is required.
	 *
	 * @param int $booking_id Booking ID.
	 */
	private static function confirm_booking( int $booking_id ): void {
		$booking = ( new Booking_Repository() )->find( $booking_id );

		if ( ! $booking ) {
			return;
		}

		$status = (string) $booking['status'];

		// The hold expired before the provider confirmed; holding the money without a seat is not an option.
		if ( 'cancelled' === $status || 'rejected' === $status ) {
			$approval = (bool) Settings::get( 'require_approval', false );
			$result   = Booking_Service::update_status( $booking_id, $approval ? 'pending' : 'confirmed' );

			if ( is_wp_error( $result ) ) {
				self::refund_booking( $booking_id );
			} elseif ( $approval ) {
				Booking_Emails::send_attendees( $booking_id, __( 'Awaiting approval', 're-events' ) );
			}

			return;
		}

		if ( 'pending' !== $status ) {
			return;
		}

		if ( Settings::get( 'require_approval', false ) ) {
			Booking_Emails::send_attendees( $booking_id, __( 'Awaiting approval', 're-events' ) );
			return;
		}

		Booking_Service::update_status( $booking_id, 'confirmed' );
	}

	/**
	 * Mark pending payments of a booking as failed.
	 *
	 * @param int $booking_id Booking ID.
	 */
	public static function cancel_pending( int $booking_id ): void {
		$repo = new Payment_Repository();

		foreach ( $repo->for_booking( $booking_id ) as $payment ) {
			if ( 'pending' === (string) $payment['status'] ) {
				$repo->update( (int) $payment['id'], [ 'status' => 'failed' ] );
			}
		}
	}
}

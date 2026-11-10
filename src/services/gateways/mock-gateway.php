<?php
/**
 * Mock payment gateway.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Services\Gateways;

use WP_Error;

/**
 * Local gateway for tests and manual checkout checks.
 */
final class Mock_Gateway implements Gateway {

	/**
	 * Gateway identifier.
	 */
	public function id(): string {
		return 'mock';
	}

	/**
	 * Create a payment.
	 *
	 * @param array<string, mixed> $payment Payment row.
	 * @param array<string, mixed> $booking Booking row.
	 * @return array{gateway_ref:string,status:string,redirect_url:string,data:array<string, mixed>}
	 */
	public function create_payment( array $payment, array $booking ) {
		return [
			'gateway_ref'  => 'mock_' . wp_generate_password( 24, false, false ),
			'status'       => 'pending',
			'redirect_url' => '',
			'data'         => [],
		];
	}

	/**
	 * Verify a webhook.
	 *
	 * @param string               $payload Raw request body.
	 * @param array<string, mixed> $headers Request headers.
	 * @return array{gateway_ref:string,status:string}|WP_Error
	 */
	public function verify_webhook( string $payload, array $headers ) {
		if ( ! defined( 'RE_EVENTS_TESTING' ) || ! RE_EVENTS_TESTING ) {
			return new WP_Error( 'reevents_payment_invalid_gateway', __( 'The mock gateway is only available for tests.', 're-events' ), [ 'status' => 403 ] );
		}

		$data = json_decode( $payload, true );

		if ( ! is_array( $data ) || empty( $data['gateway_ref'] ) ) {
			return new WP_Error( 'reevents_payment_invalid_webhook', __( 'Invalid payment webhook.', 're-events' ), [ 'status' => 400 ] );
		}

		$status = (string) ( $data['status'] ?? 'failed' );

		if ( ! in_array( $status, [ 'succeeded', 'failed', 'refunded' ], true ) ) {
			$status = 'failed';
		}

		return [
			'gateway_ref' => (string) $data['gateway_ref'],
			'status'      => $status,
		];
	}

	/**
	 * Refund a payment.
	 *
	 * @param string $gateway_ref Gateway reference.
	 * @param int    $amount_minor Amount in the smallest currency unit, or 0 for full.
	 * @return bool
	 */
	public function refund( string $gateway_ref, int $amount_minor ) {
		return '' !== $gateway_ref;
	}

	/**
	 * Read the current gateway status.
	 *
	 * @param string $gateway_ref Gateway reference.
	 * @return array{status:string}
	 */
	public function sync( string $gateway_ref ) {
		return [ 'status' => 'pending' ];
	}
}

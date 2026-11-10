<?php
/**
 * Payment gateway contract.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Services\Gateways;

use WP_Error;

/**
 * A payment provider adapter.
 */
interface Gateway {

	/**
	 * Gateway identifier.
	 */
	public function id(): string;

	/**
	 * Create a payment.
	 *
	 * @param array<string, mixed> $payment Payment row.
	 * @param array<string, mixed> $booking Booking row.
	 * @return array{gateway_ref:string,status:string,redirect_url:string,data:array<string, mixed>}|WP_Error
	 */
	public function create_payment( array $payment, array $booking );

	/**
	 * Verify and normalize a webhook.
	 *
	 * @param string               $payload Raw request body.
	 * @param array<string, mixed> $headers Request headers.
	 * @return array{gateway_ref:string,status:string,payment_id?:int,payment_intent?:string,refunded_minor?:int,refund_cumulative?:bool}|WP_Error
	 */
	public function verify_webhook( string $payload, array $headers );

	/**
	 * Refund a payment.
	 *
	 * @param string $gateway_ref Gateway reference.
	 * @param int    $amount_minor Amount in the smallest currency unit, or 0 for full.
	 * @return bool|WP_Error
	 */
	public function refund( string $gateway_ref, int $amount_minor );

	/**
	 * Read the current gateway status.
	 *
	 * @param string $gateway_ref Gateway reference.
	 * @param bool   $read_only    Read only; do not capture or complete the payment.
	 * @return array{status:string}|WP_Error
	 */
	public function sync( string $gateway_ref, bool $read_only = false );
}

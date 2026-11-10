<?php
/**
 * PayPal payment gateway.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Services\Gateways;

use ReEvents\Admin\Settings;
use ReEvents\Services\Currency;
use WP_Error;

/**
 * PayPal Orders v2 with webhook verification.
 */
final class PayPal_Gateway implements Gateway {

	/**
	 * Gateway identifier.
	 */
	public function id(): string {
		return 'paypal';
	}

	/**
	 * Create an order.
	 *
	 * @param array<string, mixed> $payment Payment row.
	 * @param array<string, mixed> $booking Booking row.
	 * @return array{gateway_ref:string,status:string,redirect_url:string,data:array<string, mixed>}|WP_Error
	 */
	public function create_payment( array $payment, array $booking ) {
		$booking_id = (int) ( $booking['id'] ?? 0 );
		$payment_id = (int) ( $payment['id'] ?? 0 );
		$name       = $booking_id > 0 ? get_the_title( (int) ( $booking['event_id'] ?? 0 ) ) : '';

		if ( '' === $name ) {
			/* translators: %d: booking ID. */
			$name = sprintf( __( 'Booking #%d', 're-events' ), $booking_id );
		}

		$result = $this->request(
			'POST',
			'v2/checkout/orders',
			[
				'intent'              => 'CAPTURE',
				'purchase_units'      => [
					[
						'amount'      => [
							'currency_code' => strtoupper( (string) ( $payment['currency'] ?? 'USD' ) ),
							'value'         => Currency::amount( (int) ( $payment['amount_minor'] ?? 0 ), (string) ( $payment['currency'] ?? 'USD' ) ),
						],
						'description' => $name,
						'custom_id'   => (string) $booking_id,
					],
				],
				'application_context' => [
					'return_url'          => $this->return_url( 'success', $booking_id, (string) ( $booking['reference'] ?? '' ) ),
					'cancel_url'          => $this->return_url( 'cancel', $booking_id, (string) ( $booking['reference'] ?? '' ) ),
					'user_action'         => 'PAY_NOW',
					'shipping_preference' => 'NO_SHIPPING',
				],
			],
			[ 'PayPal-Request-Id' => 'reevents_payment_' . $payment_id ]
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$redirect = '';

		foreach ( (array) ( $result['links'] ?? [] ) as $link ) {
			if ( is_array( $link ) && 'approve' === ( $link['rel'] ?? '' ) ) {
				$redirect = (string) ( $link['href'] ?? '' );
			}
		}

		return [
			'gateway_ref'  => (string) ( $result['id'] ?? '' ),
			'status'       => 'pending',
			'redirect_url' => $redirect,
			'data'         => [ 'order_id' => (string) ( $result['id'] ?? '' ) ],
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
		$webhook_id = (string) Settings::get( 'paypal_webhook_id', '' );

		if ( '' === $webhook_id ) {
			return new WP_Error( 'reevents_payment_missing_secret', __( 'PayPal webhooks are not configured.', 're-events' ), [ 'status' => 400 ] );
		}

		$event = json_decode( $payload, true );

		if ( ! is_array( $event ) ) {
			return new WP_Error( 'reevents_payment_invalid_webhook', __( 'Invalid payment webhook.', 're-events' ), [ 'status' => 400 ] );
		}

		$verified = $this->request(
			'POST',
			'v1/notifications/verify-webhook-signature',
			[
				'auth_algo'         => $this->header( $headers, 'paypal_auth_algo' ),
				'cert_url'          => $this->header( $headers, 'paypal_cert_url' ),
				'transmission_id'   => $this->header( $headers, 'paypal_transmission_id' ),
				'transmission_sig'  => $this->header( $headers, 'paypal_transmission_sig' ),
				'transmission_time' => $this->header( $headers, 'paypal_transmission_time' ),
				'webhook_id'        => $webhook_id,
				'webhook_event'     => $event,
			]
		);

		if ( is_wp_error( $verified ) ) {
			return $verified;
		}

		if ( 'SUCCESS' !== (string) ( $verified['verification_status'] ?? '' ) ) {
			return new WP_Error( 'reevents_payment_invalid_signature', __( 'Invalid webhook signature.', 're-events' ), [ 'status' => 400 ] );
		}

		$type     = (string) ( $event['event_type'] ?? '' );
		$resource = (array) ( $event['resource'] ?? [] );
		$ref      = (string) ( $resource['supplementary_data']['related_ids']['order_id'] ?? '' );

		if ( '' === $ref && 'CHECKOUT.ORDER.APPROVED' === $type ) {
			$ref = (string) ( $resource['id'] ?? '' );
		}

		if ( '' === $ref ) {
			return new WP_Error( 'reevents_payment_invalid_webhook', __( 'Invalid payment webhook.', 're-events' ), [ 'status' => 400 ] );
		}

		switch ( $type ) {
			case 'PAYMENT.CAPTURE.COMPLETED':
				$status = 'succeeded';
				break;
			case 'PAYMENT.CAPTURE.DENIED':
				$status = 'failed';
				break;
			case 'PAYMENT.CAPTURE.REFUNDED':
				$status = 'refunded';
				break;
			case 'CHECKOUT.ORDER.APPROVED':
				$status = 'pending';
				break;
			default:
				$status = 'ignored';
		}

		return [
			'gateway_ref' => $ref,
			'status'      => $status,
		];
	}

	/**
	 * Refund a captured payment.
	 *
	 * @param string $gateway_ref Order ID.
	 * @param int    $amount_minor Amount in the smallest currency unit, or 0 for full.
	 * @return bool|WP_Error
	 */
	public function refund( string $gateway_ref, int $amount_minor ) {
		$order = $this->request( 'GET', 'v2/checkout/orders/' . rawurlencode( $gateway_ref ) );

		if ( is_wp_error( $order ) ) {
			return $order;
		}

		$capture = (array) ( $order['purchase_units'][0]['payments']['captures'][0] ?? [] );

		if ( empty( $capture['id'] ) ) {
			return new WP_Error( 'reevents_payment_refund_failed', __( 'The refund could not be created.', 're-events' ), [ 'status' => 500 ] );
		}

		$params = [];

		if ( $amount_minor > 0 ) {
			$currency = (string) ( $capture['amount']['currency_code'] ?? 'USD' );

			$params['amount'] = [
				'currency_code' => $currency,
				'value'         => Currency::amount( $amount_minor, $currency ),
			];
		}

		$result = $this->request( 'POST', 'v2/payments/captures/' . rawurlencode( (string) $capture['id'] ) . '/refund', $params );

		return ! is_wp_error( $result );
	}

	/**
	 * Read the order status and capture an approved order.
	 *
	 * @param string $gateway_ref Order ID.
	 * @return array{status:string}|WP_Error
	 */
	public function sync( string $gateway_ref ) {
		$order = $this->request( 'GET', 'v2/checkout/orders/' . rawurlencode( $gateway_ref ) );

		if ( is_wp_error( $order ) ) {
			return $order;
		}

		$status = (string) ( $order['status'] ?? '' );

		if ( 'APPROVED' === $status ) {
			$captured = $this->request( 'POST', 'v2/checkout/orders/' . rawurlencode( $gateway_ref ) . '/capture' );
			$status   = is_wp_error( $captured ) ? 'pending' : (string) ( $captured['status'] ?? '' );
		}

		if ( 'COMPLETED' === $status ) {
			return [ 'status' => 'succeeded' ];
		}

		if ( in_array( $status, [ 'VOIDED', 'PAYER_ACTION_REQUIRED' ], true ) ) {
			return [ 'status' => 'failed' ];
		}

		return [ 'status' => 'pending' ];
	}

	/**
	 * Read a header by normalized name.
	 *
	 * @param array<string, mixed> $headers Headers.
	 * @param string               $name    Normalized name.
	 */
	private function header( array $headers, string $name ): string {
		foreach ( $headers as $key => $value ) {
			if ( strtolower( str_replace( '-', '_', (string) $key ) ) !== $name ) {
				continue;
			}

			return is_array( $value ) ? (string) reset( $value ) : (string) $value;
		}

		return '';
	}

	/**
	 * Build a return URL.
	 *
	 * @param string $result     `success` or `cancel`.
	 * @param int    $booking_id Booking ID.
	 * @param string $reference  Booking reference.
	 */
	private function return_url( string $result, int $booking_id, string $reference ): string {
		$url = (string) Settings::get( 'paypal_' . $result . '_url', '' );

		if ( '' === $url ) {
			$url = home_url( '/' );
		}

		return add_query_arg(
			[
				'reevents_payment' => $result,
				'booking'          => $booking_id,
				'token'            => $reference,
			],
			$url
		);
	}

	/**
	 * Get an API access token.
	 *
	 * @return string|WP_Error
	 */
	private function token() {
		$client_id = (string) Settings::get( 'paypal_client_id', '' );
		$secret    = (string) Settings::get( 'paypal_client_secret', '' );

		if ( '' === $client_id || '' === $secret ) {
			return new WP_Error( 'reevents_payment_missing_key', __( 'PayPal is not configured.', 're-events' ), [ 'status' => 400 ] );
		}

		$key   = 'reevents_paypal_token_' . md5( $this->mode() . '|' . $client_id );
		$token = get_transient( $key );

		if ( is_string( $token ) && '' !== $token ) {
			return $token;
		}

		$response = wp_remote_post(
			$this->base() . 'v1/oauth2/token',
			[
				'timeout' => 20,
				'headers' => [
					'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $secret ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP Basic authentication.
					'Content-Type'  => 'application/x-www-form-urlencoded',
				],
				'body'    => [ 'grant_type' => 'client_credentials' ],
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data  = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$token = is_array( $data ) ? (string) ( $data['access_token'] ?? '' ) : '';

		if ( '' === $token ) {
			return new WP_Error( 'reevents_payment_request_failed', __( 'The payment provider request failed.', 're-events' ), [ 'status' => 502 ] );
		}

		set_transient( $key, $token, max( 60, (int) ( $data['expires_in'] ?? 3600 ) - 60 ) );

		return $token;
	}

	/**
	 * Call the PayPal API.
	 *
	 * @param string                $method  HTTP method.
	 * @param string                $path    API path.
	 * @param array<string, mixed>  $params  Request parameters.
	 * @param array<string, string> $headers Extra headers.
	 * @return array<string, mixed>|WP_Error
	 */
	private function request( string $method, string $path, array $params = [], array $headers = [] ) {
		$token = $this->token();

		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$response = wp_remote_request(
			$this->base() . $path,
			[
				'method'  => $method,
				'timeout' => 20,
				'headers' => array_merge(
					[
						'Authorization' => 'Bearer ' . $token,
						'Content-Type'  => 'application/json',
					],
					$headers
				),
				'body'    => 'GET' === $method ? null : ( $params ? (string) wp_json_encode( $params ) : '{}' ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( $code >= 400 || ! is_array( $data ) ) {
			$message = is_array( $data ) ? (string) ( $data['message'] ?? $data['error_description'] ?? '' ) : '';

			return new WP_Error( 'reevents_payment_request_failed', '' !== $message ? $message : __( 'The payment provider request failed.', 're-events' ), [ 'status' => 502 ] );
		}

		return $data;
	}

	/**
	 * API base URL.
	 */
	private function base(): string {
		return 'live' === $this->mode() ? 'https://api-m.paypal.com/' : 'https://api-m.sandbox.paypal.com/';
	}

	/**
	 * Current PayPal mode.
	 */
	private function mode(): string {
		return 'live' === (string) Settings::get( 'paypal_mode', 'sandbox' ) ? 'live' : 'sandbox';
	}
}

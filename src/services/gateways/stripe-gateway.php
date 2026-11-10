<?php
/**
 * Stripe payment gateway.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Services\Gateways;

use ReEvents\Admin\Settings;
use ReEvents\Services\Currency;
use WP_Error;

/**
 * Stripe Checkout Sessions with signed webhooks.
 */
final class Stripe_Gateway implements Gateway {

	/**
	 * Stripe API base URL.
	 */
	private const API = 'https://api.stripe.com/v1/';

	/**
	 * Maximum accepted webhook clock skew in seconds.
	 */
	private const TOLERANCE = 300;

	/**
	 * Gateway identifier.
	 */
	public function id(): string {
		return 'stripe';
	}

	/**
	 * Create a Checkout Session.
	 *
	 * @param array<string, mixed> $payment Payment row.
	 * @param array<string, mixed> $booking Booking row.
	 * @return array{gateway_ref:string,status:string,redirect_url:string,data:array<string, mixed>}|WP_Error
	 */
	public function create_payment( array $payment, array $booking ) {
		$secret = (string) Settings::get( 'stripe_secret_key', '' );

		if ( '' === $secret ) {
			return new WP_Error( 'reevents_payment_missing_key', __( 'Stripe is not configured.', 're-events' ), [ 'status' => 400 ] );
		}

		$booking_id = (int) ( $booking['id'] ?? 0 );
		$payment_id = (int) ( $payment['id'] ?? 0 );
		$name       = $booking_id > 0 ? get_the_title( (int) ( $booking['event_id'] ?? 0 ) ) : '';

		if ( '' === $name ) {
			/* translators: %d: booking ID. */
			$name = sprintf( __( 'Booking #%d', 're-events' ), $booking_id );
		}

		$result = $this->request(
			'POST',
			'checkout/sessions',
			[
				'mode'                                   => 'payment',
				'success_url'                            => $this->return_url( 'success', $booking_id, (string) ( $booking['reference'] ?? '' ) ),
				'cancel_url'                             => $this->return_url( 'cancel', $booking_id, (string) ( $booking['reference'] ?? '' ) ),
				'client_reference_id'                    => (string) $booking_id,
				'metadata[booking_id]'                   => (string) $booking_id,
				'metadata[payment_id]'                   => (string) $payment_id,
				'line_items[0][quantity]'                => '1',
				'line_items[0][price_data][currency]'    => strtolower( (string) ( $payment['currency'] ?? 'usd' ) ),
				'line_items[0][price_data][unit_amount]' => (string) Currency::stripe_amount( (int) ( $payment['amount_minor'] ?? 0 ), (string) ( $payment['currency'] ?? 'USD' ) ),
				'line_items[0][price_data][product_data][name]' => $name,
			],
			[ 'Idempotency-Key' => 'reevents_payment_' . $payment_id ]
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return [
			'gateway_ref'  => (string) ( $result['id'] ?? '' ),
			'status'       => 'pending',
			'redirect_url' => (string) ( $result['url'] ?? '' ),
			'data'         => [ 'session_id' => (string) ( $result['id'] ?? '' ) ],
		];
	}

	/**
	 * Verify a signed webhook.
	 *
	 * @param string               $payload Raw request body.
	 * @param array<string, mixed> $headers Request headers.
	 * @return array{gateway_ref:string,status:string}|WP_Error
	 */
	public function verify_webhook( string $payload, array $headers ) {
		$secret = (string) Settings::get( 'stripe_webhook_secret', '' );

		if ( '' === $secret ) {
			return new WP_Error( 'reevents_payment_missing_secret', __( 'Stripe webhooks are not configured.', 're-events' ), [ 'status' => 400 ] );
		}

		$signature = Gateway_Utils::header( $headers, 'stripe_signature' );

		if ( '' === $signature || ! self::verify_signature( $payload, $signature, $secret ) ) {
			return new WP_Error( 'reevents_payment_invalid_signature', __( 'Invalid webhook signature.', 're-events' ), [ 'status' => 400 ] );
		}

		$event = json_decode( $payload, true );

		if ( ! is_array( $event ) ) {
			return new WP_Error( 'reevents_payment_invalid_webhook', __( 'Invalid payment webhook.', 're-events' ), [ 'status' => 400 ] );
		}

		$type   = (string) ( $event['type'] ?? '' );
		$object = (array) ( $event['data']['object'] ?? [] );
		$ref    = (string) ( $object['id'] ?? '' );

		if ( '' === $ref ) {
			return new WP_Error( 'reevents_payment_invalid_webhook', __( 'Invalid payment webhook.', 're-events' ), [ 'status' => 400 ] );
		}

		switch ( $type ) {
			case 'checkout.session.completed':
				$status = 'paid' === (string) ( $object['payment_status'] ?? '' ) ? 'succeeded' : 'pending';
				break;
			case 'checkout.session.async_payment_succeeded':
				$status = 'succeeded';
				break;
			case 'checkout.session.expired':
			case 'checkout.session.async_payment_failed':
				$status = 'failed';
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
	 * Refund a Checkout Session payment.
	 *
	 * @param string $gateway_ref Session ID.
	 * @param int    $amount_minor Amount in the smallest currency unit, or 0 for full.
	 * @return bool|WP_Error
	 */
	public function refund( string $gateway_ref, int $amount_minor ) {
		$session = $this->request( 'GET', 'checkout/sessions/' . rawurlencode( $gateway_ref ) );

		if ( is_wp_error( $session ) ) {
			return $session;
		}

		$intent = (string) ( $session['payment_intent'] ?? '' );

		if ( '' === $intent ) {
			return new WP_Error( 'reevents_payment_refund_failed', __( 'The refund could not be created.', 're-events' ), [ 'status' => 500 ] );
		}

		$params = [ 'payment_intent' => $intent ];

		if ( $amount_minor > 0 ) {
			$params['amount'] = (string) $amount_minor;
		}

		$result = $this->request( 'POST', 'refunds', $params );

		return ! is_wp_error( $result );
	}

	/**
	 * Read the Checkout Session status.
	 *
	 * @param string $gateway_ref Session ID.
	 * @return array{status:string}|WP_Error
	 */
	public function sync( string $gateway_ref ) {
		$session = $this->request( 'GET', 'checkout/sessions/' . rawurlencode( $gateway_ref ) );

		if ( is_wp_error( $session ) ) {
			return $session;
		}

		if ( 'expired' === (string) ( $session['status'] ?? '' ) ) {
			return [ 'status' => 'failed' ];
		}

		if ( 'complete' === (string) ( $session['status'] ?? '' ) && 'paid' === (string) ( $session['payment_status'] ?? '' ) ) {
			return [ 'status' => 'succeeded' ];
		}

		return [ 'status' => 'pending' ];
	}

	/**
	 * Verify a Stripe signature header.
	 *
	 * @param string $payload   Raw body.
	 * @param string $header    Stripe-Signature header.
	 * @param string $secret    Webhook secret.
	 * @param int    $now       Current timestamp.
	 * @param int    $tolerance Allowed clock skew in seconds.
	 */
	public static function verify_signature( string $payload, string $header, string $secret, ?int $now = null, int $tolerance = self::TOLERANCE ): bool {
		$timestamp  = 0;
		$signatures = [];

		foreach ( explode( ',', $header ) as $part ) {
			$pair = explode( '=', trim( $part ), 2 );

			if ( 2 !== count( $pair ) ) {
				continue;
			}

			if ( 't' === $pair[0] ) {
				$timestamp = (int) $pair[1];
			} elseif ( 'v1' === $pair[0] ) {
				$signatures[] = $pair[1];
			}
		}

		if ( $timestamp <= 0 || ! $signatures ) {
			return false;
		}

		if ( abs( ( $now ?? time() ) - $timestamp ) > $tolerance ) {
			return false;
		}

		$expected = hash_hmac( 'sha256', $timestamp . '.' . $payload, $secret );

		foreach ( $signatures as $signature ) {
			if ( hash_equals( $expected, $signature ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Build a return URL.
	 *
	 * @param string $result     `success` or `cancel`.
	 * @param int    $booking_id Booking ID.
	 * @param string $reference  Booking reference.
	 */
	private function return_url( string $result, int $booking_id, string $reference ): string {
		$url = (string) Settings::get( 'stripe_' . $result . '_url', '' );

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
	 * Call the Stripe API.
	 *
	 * @param string                $method HTTP method.
	 * @param string                $path   API path.
	 * @param array<string, mixed>  $params Request parameters.
	 * @param array<string, string> $headers Extra headers.
	 * @return array<string, mixed>|WP_Error
	 */
	private function request( string $method, string $path, array $params = [], array $headers = [] ) {
		$secret = (string) Settings::get( 'stripe_secret_key', '' );

		if ( '' === $secret ) {
			return new WP_Error( 'reevents_payment_missing_key', __( 'Stripe is not configured.', 're-events' ), [ 'status' => 400 ] );
		}

		$url = self::API . $path;

		if ( 'GET' === $method && $params ) {
			$url = add_query_arg( $params, $url );
		}

		$response = wp_remote_request(
			$url,
			[
				'method'  => $method,
				'timeout' => 20,
				'headers' => array_merge(
					[
						'Authorization' => 'Bearer ' . $secret,
						'Content-Type'  => 'application/x-www-form-urlencoded',
					],
					$headers
				),
				'body'    => 'POST' === $method ? $params : null,
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( $code >= 400 || ! is_array( $data ) ) {
			$message = is_array( $data ) && isset( $data['error']['message'] ) ? (string) $data['error']['message'] : __( 'The payment provider request failed.', 're-events' );

			return new WP_Error( 'reevents_payment_request_failed', $message, [ 'status' => 502 ] );
		}

		return $data;
	}
}

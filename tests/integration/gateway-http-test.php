<?php
/**
 * Gateway HTTP integration tests.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Tests\Integration;

use ReEvents\Services\Gateways\PayPal_Gateway;
use ReEvents\Services\Gateways\Stripe_Gateway;
use WP_UnitTestCase;

/**
 * Covers gateway requests against mocked HTTP responses.
 */
final class Gateway_HTTP_Test extends WP_UnitTestCase {

	/**
	 * Captured requests.
	 *
	 * @var array<int, array{url:string,args:array<string, mixed>}>
	 */
	private array $requests = [];

	/**
	 * Original settings.
	 *
	 * @var array<string, mixed>
	 */
	private array $settings = [];

	/**
	 * Save settings.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->settings = (array) get_option( 'reevents_settings', [] );
	}

	/**
	 * Restore settings and stubs.
	 */
	public function tear_down(): void {
		remove_all_filters( 'pre_http_request' );
		delete_transient( 'reevents_paypal_token_' . md5( 'sandbox|test-client' ) );
		update_option( 'reevents_settings', $this->settings );

		parent::tear_down();
	}

	/**
	 * Set settings.
	 *
	 * @param array<string, mixed> $values Values.
	 */
	private function settings( array $values ): void {
		update_option( 'reevents_settings', array_merge( (array) get_option( 'reevents_settings', [] ), $values ) );
	}

	/**
	 * Stub HTTP responses.
	 *
	 * @param callable $handler Response builder.
	 */
	private function mock( callable $handler ): void {
		add_filter(
			'pre_http_request',
			function ( $pre, $args, $url ) use ( $handler ) {
				if ( false !== $pre ) {
					return $pre;
				}

				$this->requests[] = [
					'url'  => (string) $url,
					'args' => (array) $args,
				];

				return $handler( (string) $url, (array) $args );
			},
			10,
			3
		);
	}

	/**
	 * Build a response.
	 *
	 * @param array<string, mixed> $data Body.
	 * @param int                  $code Status code.
	 * @return array<string, mixed>
	 */
	private function respond( array $data, int $code = 200 ): array {
		return [
			'headers'  => [],
			'body'     => (string) wp_json_encode( $data ),
			'response' => [
				'code'    => $code,
				'message' => '',
			],
			'cookies'  => [],
			'filename' => null,
		];
	}

	/**
	 * Stripe sends the stored minor amount for zero-decimal currencies.
	 */
	public function test_stripe_checkout_amount(): void {
		$this->settings( [ 'stripe_secret_key' => 'sk_test' ] );
		$this->mock(
			fn() => $this->respond(
				[
					'id'  => 'cs_1',
					'url' => 'https://checkout.test/cs_1',
				]
			)
		);

		$result = ( new Stripe_Gateway() )->create_payment(
			[
				'id'           => 7,
				'amount_minor' => 500,
				'currency'     => 'JPY',
			],
			[
				'id'        => 9,
				'reference' => 'ref',
				'event_id'  => 0,
			]
		);

		self::assertIsArray( $result );
		self::assertSame( 'cs_1', $result['gateway_ref'] );
		self::assertSame( 'https://checkout.test/cs_1', $result['redirect_url'] );

		$body = $this->requests[0]['args']['body'];
		self::assertSame( '500', $body['line_items[0][price_data][unit_amount]'] );
		self::assertSame( 'jpy', $body['line_items[0][price_data][currency]'] );
		self::assertStringContainsString( 'token=ref', (string) $body['success_url'] );
	}

	/**
	 * PayPal creates an order with a whole-unit value.
	 */
	public function test_paypal_order_amount(): void {
		$this->settings(
			[
				'paypal_mode'          => 'sandbox',
				'paypal_client_id'     => 'test-client',
				'paypal_client_secret' => 'test-secret',
			]
		);

		$this->mock(
			function ( string $url ) {
				if ( str_contains( $url, 'oauth2/token' ) ) {
					return $this->respond(
						[
							'access_token' => 'token',
							'expires_in'   => 3600,
						]
					);
				}

				return $this->respond(
					[
						'id'    => 'order_1',
						'links' => [
							[
								'rel'  => 'approve',
								'href' => 'https://paypal.test/approve',
							],
						],
					]
				);
			}
		);

		$result = ( new PayPal_Gateway() )->create_payment(
			[
				'id'           => 7,
				'amount_minor' => 500,
				'currency'     => 'JPY',
			],
			[
				'id'        => 9,
				'reference' => 'ref',
				'event_id'  => 0,
			]
		);

		self::assertIsArray( $result );
		self::assertSame( 'order_1', $result['gateway_ref'] );
		self::assertSame( 'https://paypal.test/approve', $result['redirect_url'] );

		$order = $this->requests[ count( $this->requests ) - 1 ];
		$body  = json_decode( (string) $order['args']['body'], true );

		self::assertSame( '500', $body['purchase_units'][0]['amount']['value'] );
		self::assertSame( 'JPY', $body['purchase_units'][0]['amount']['currency_code'] );
	}

	/**
	 * An approved PayPal order is captured on sync.
	 */
	public function test_paypal_sync_captures_order(): void {
		$this->settings(
			[
				'paypal_mode'          => 'sandbox',
				'paypal_client_id'     => 'test-client',
				'paypal_client_secret' => 'test-secret',
			]
		);

		$this->mock(
			function ( string $url, array $args ) {
				if ( str_contains( $url, 'oauth2/token' ) ) {
					return $this->respond(
						[
							'access_token' => 'token',
							'expires_in'   => 3600,
						]
					);
				}

				if ( 'POST' === ( $args['method'] ?? '' ) && str_ends_with( $url, '/capture' ) ) {
					return $this->respond( [ 'status' => 'COMPLETED' ] );
				}

				return $this->respond( [ 'status' => 'APPROVED' ] );
			}
		);

		$result = ( new PayPal_Gateway() )->sync( 'order_1' );

		self::assertSame( [ 'status' => 'succeeded' ], $result );
	}

	/**
	 * A read-only PayPal sync does not capture an approved order.
	 */
	public function test_paypal_read_only_sync_does_not_capture(): void {
		$this->settings(
			[
				'paypal_mode'          => 'sandbox',
				'paypal_client_id'     => 'test-client',
				'paypal_client_secret' => 'test-secret',
			]
		);

		$captured = false;

		$this->mock(
			function ( string $url, array $args ) use ( &$captured ) {
				if ( str_contains( $url, 'oauth2/token' ) ) {
					return $this->respond(
						[
							'access_token' => 'token',
							'expires_in'   => 3600,
						]
					);
				}

				if ( 'POST' === ( $args['method'] ?? '' ) && str_ends_with( $url, '/capture' ) ) {
					$captured = true;

					return $this->respond( [ 'status' => 'COMPLETED' ] );
				}

				return $this->respond( [ 'status' => 'APPROVED' ] );
			}
		);

		$result = ( new PayPal_Gateway() )->sync( 'order_1', true );

		self::assertFalse( $captured );
		self::assertSame( [ 'status' => 'pending' ], $result );
	}

	/**
	 * A Stripe charge refund maps through the payment intent.
	 */
	public function test_stripe_refund_webhook(): void {
		$this->settings(
			[
				'stripe_webhook_secret' => 'whsec_test',
			]
		);

		$payload   = (string) wp_json_encode(
			[
				'type' => 'charge.refunded',
				'data' => [
					'object' => [
						'id'              => 'ch_1',
						'amount'          => 1000,
						'amount_refunded' => 1000,
						'payment_intent'  => 'pi_1',
						'metadata'        => [],
					],
				],
			]
		);
		$time      = time();
		$signature = 't=' . $time . ',v1=' . hash_hmac( 'sha256', $time . '.' . $payload, 'whsec_test' );
		$result    = ( new Stripe_Gateway() )->verify_webhook( $payload, [ 'Stripe-Signature' => $signature ] );

		self::assertIsArray( $result );
		self::assertSame( 'refunded', $result['status'] );
		self::assertSame( 'pi_1', $result['payment_intent'] );
		self::assertSame( 1000, $result['refunded_minor'] );
	}

	/**
	 * PayPal refunds carry the refunded amount.
	 */
	public function test_paypal_refund_webhook_amount(): void {
		$this->settings(
			[
				'paypal_mode'          => 'sandbox',
				'paypal_client_id'     => 'test-client',
				'paypal_client_secret' => 'test-secret',
				'paypal_webhook_id'    => 'wh_1',
			]
		);

		$this->mock(
			function ( string $url ) {
				if ( str_contains( $url, 'oauth2/token' ) ) {
					return $this->respond(
						[
							'access_token' => 'token',
							'expires_in'   => 3600,
						]
					);
				}

				return $this->respond( [ 'verification_status' => 'SUCCESS' ] );
			}
		);

		$payload = (string) wp_json_encode(
			[
				'event_type' => 'PAYMENT.CAPTURE.REFUNDED',
				'resource'   => [
					'id'                 => 'refund_1',
					'amount'             => [
						'currency_code' => 'USD',
						'value'         => '4.00',
					],
					'supplementary_data' => [
						'related_ids' => [
							'order_id' => 'order_1',
						],
					],
				],
			]
		);

		$result = ( new PayPal_Gateway() )->verify_webhook( $payload, [] );

		self::assertIsArray( $result );
		self::assertSame( 'refunded', $result['status'] );
		self::assertSame( 400, $result['refunded_minor'] );
		self::assertFalse( $result['refund_cumulative'] );
	}

	/**
	 * PayPal refunds the captured amount.
	 */
	public function test_paypal_refund(): void {
		$this->settings(
			[
				'paypal_mode'          => 'sandbox',
				'paypal_client_id'     => 'test-client',
				'paypal_client_secret' => 'test-secret',
			]
		);

		$this->mock(
			function ( string $url ) {
				if ( str_contains( $url, 'oauth2/token' ) ) {
					return $this->respond(
						[
							'access_token' => 'token',
							'expires_in'   => 3600,
						]
					);
				}

				if ( str_contains( $url, '/refund' ) ) {
					return $this->respond( [ 'id' => 'refund_1' ] );
				}

				return $this->respond(
					[
						'purchase_units' => [
							[
								'payments' => [
									'captures' => [
										[
											'id'     => 'cap_1',
											'amount' => [
												'currency_code' => 'JPY',
												'value' => '500',
											],
										],
									],
								],
							],
						],
					]
				);
			}
		);

		self::assertTrue( ( new PayPal_Gateway() )->refund( 'order_1', 500 ) );

		$refund = $this->requests[ count( $this->requests ) - 1 ];
		$body   = json_decode( (string) $refund['args']['body'], true );

		self::assertSame( '500', $body['amount']['value'] );
		self::assertSame( 'JPY', $body['amount']['currency_code'] );
	}
}

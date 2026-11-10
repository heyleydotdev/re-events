<?php
/**
 * Stripe gateway tests.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReEvents\Services\Gateways\Stripe_Gateway;

/**
 * Covers Stripe webhook signature verification.
 */
final class StripeGatewayTest extends TestCase {

	/**
	 * Payload used in the signature tests.
	 */
	private const PAYLOAD = '{"id":"evt_1","type":"checkout.session.completed"}';

	/**
	 * Secret used in the signature tests.
	 */
	private const SECRET = 'whsec_test';

	/**
	 * Build a signature header.
	 *
	 * @param int    $timestamp Timestamp.
	 * @param string $payload   Payload.
	 */
	private function header( int $timestamp, string $payload = self::PAYLOAD ): string {
		return 't=' . $timestamp . ',v1=' . hash_hmac( 'sha256', $timestamp . '.' . $payload, self::SECRET );
	}

	/**
	 * A fresh, correct signature is accepted.
	 */
	public function test_valid_signature(): void {
		$now = 1700000000;

		self::assertTrue( Stripe_Gateway::verify_signature( self::PAYLOAD, $this->header( $now ), self::SECRET, $now ) );
		self::assertTrue( Stripe_Gateway::verify_signature( self::PAYLOAD, $this->header( $now ), self::SECRET, $now + 100 ) );
	}

	/**
	 * A tampered payload is rejected.
	 */
	public function test_tampered_payload(): void {
		$now = 1700000000;

		self::assertFalse( Stripe_Gateway::verify_signature( self::PAYLOAD . ' ', $this->header( $now ), self::SECRET, $now ) );
	}

	/**
	 * An old timestamp is rejected.
	 */
	public function test_stale_signature(): void {
		$now = 1700000000;

		self::assertFalse( Stripe_Gateway::verify_signature( self::PAYLOAD, $this->header( $now ), self::SECRET, $now + 301 ) );
	}

	/**
	 * A header without a v1 signature is rejected.
	 */
	public function test_missing_signature(): void {
		$now = 1700000000;

		self::assertFalse( Stripe_Gateway::verify_signature( self::PAYLOAD, 't=' . $now, self::SECRET, $now ) );
	}

	/**
	 * Any matching v1 signature is accepted.
	 */
	public function test_multiple_signatures(): void {
		$now = 1700000000;

		$header = 't=' . $now . ',v1=deadbeef,' . substr( $this->header( $now ), strlen( 't=' . $now . ',' ) );

		self::assertTrue( Stripe_Gateway::verify_signature( self::PAYLOAD, $header, self::SECRET, $now ) );
	}
}

<?php
/**
 * Webhook rate limit tests.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Tests\Integration;

use ReEvents\REST\Routes;
use ReEvents\Services\Rate_Limit;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Covers the anonymous webhook budget.
 */
final class Webhook_Rate_Limit_Test extends WP_UnitTestCase {

	/**
	 * Requests over the webhook budget return 429.
	 */
	public function test_webhook_budget(): void {
		$_SERVER['REMOTE_ADDR'] = '198.51.100.77';

		for ( $i = 0; $i < 120; $i++ ) {
			Rate_Limit::hit( 'webhook', 120, MINUTE_IN_SECONDS );
		}

		$request = new WP_REST_Request( 'POST', '/re-events/v1/webhooks/mock' );
		$request->set_param( 'gateway', 'mock' );
		$request->set_body( '{}' );

		$result = Routes::webhook( $request );

		unset( $_SERVER['REMOTE_ADDR'] );

		self::assertWPError( $result );
		self::assertSame( 'rest_webhook_rate_limited', $result->get_error_code() );
	}
}

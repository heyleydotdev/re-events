<?php
/**
 * Event integration tests.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Tests\Integration;

use ReEvents\Domain\Event;
use ReEvents\Services\Event_Service;
use WP_UnitTestCase;

/**
 * Covers event validation.
 */
final class Event_Test extends WP_UnitTestCase {

	/**
	 * An invalid recurrence rule is rejected before saving.
	 */
	public function test_invalid_rrule_is_rejected(): void {
		$event = new Event(
			0,
			'Invalid rule',
			'2026-01-01 00:00:00',
			'2026-01-01 01:00:00',
			'UTC',
			false,
			'FREQ=DAILY;INTERVAL=abc'
		);

		$result = Event_Service::validate( $event );

		self::assertWPError( $result );
		self::assertSame( 'rest_event_invalid_rrule', $result->get_error_code() );
	}
}

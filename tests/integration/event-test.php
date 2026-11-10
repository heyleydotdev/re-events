<?php
/**
 * Event integration tests.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Tests\Integration;

use ReEvents\Data\Meta;
use ReEvents\Domain\Event;
use ReEvents\Services\Event_Service;
use WP_UnitTestCase;

/**
 * Covers event validation.
 */
final class Event_Test extends WP_UnitTestCase {

	/**
	 * A title made only of markup is rejected.
	 */
	public function test_markup_only_title_is_rejected(): void {
		$event = new Event(
			0,
			'<b></b>',
			'2026-01-01 00:00:00',
			'2026-01-01 01:00:00',
			'UTC'
		);

		$result = Event_Service::validate( $event );

		self::assertWPError( $result );
		self::assertSame( 'rest_event_invalid_title', $result->get_error_code() );
	}

	/**
	 * A calendar date that does not exist is rejected.
	 */
	public function test_invalid_local_date_is_rejected(): void {
		self::assertSame( '', Meta::local_to_utc( '2026-02-30T10:00', 'UTC' ) );
		self::assertSame( '', Meta::local_to_utc( '2026-13-01 10:00', 'UTC' ) );
		self::assertSame( '2026-03-01 10:00:00', Meta::local_to_utc( '2026-03-01T10:00', 'UTC' ) );
	}

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

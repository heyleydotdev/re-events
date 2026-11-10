<?php
/**
 * Event submission integration tests.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Tests\Integration;

use ReEvents\Data\Occurrence_Repository;
use ReEvents\Domain\Event;
use ReEvents\Services\Event_Service;
use WP_UnitTestCase;

/**
 * Covers submitted event status and occurrences.
 */
final class Submission_Test extends WP_UnitTestCase {

	/**
	 * A submitted event stays pending and has no occurrences.
	 */
	public function test_pending_submission(): void {
		$id = Event_Service::save(
			new Event(
				0,
				'Submitted event',
				gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) ),
				gmdate( 'Y-m-d H:i:s', strtotime( '+1 day +1 hour' ) ),
				'UTC'
			),
			'pending'
		);

		self::assertIsInt( $id );
		self::assertSame( 'pending', get_post_status( $id ) );
		self::assertSame( 0, ( new Occurrence_Repository() )->count_for_event( $id ) );
	}

	/**
	 * Publishing a submitted event builds its occurrences.
	 */
	public function test_publishing_submission(): void {
		$id = Event_Service::save(
			new Event(
				0,
				'Published event',
				gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) ),
				gmdate( 'Y-m-d H:i:s', strtotime( '+1 day +1 hour' ) ),
				'UTC'
			),
			'pending'
		);

		self::assertIsInt( $id );

		wp_update_post(
			[
				'ID'          => $id,
				'post_status' => 'publish',
			]
		);

		self::assertGreaterThan( 0, ( new Occurrence_Repository() )->count_for_event( $id ) );
	}
}

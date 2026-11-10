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
use ReEvents\Frontend\Submission_Handler;
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

	/**
	 * Only the owner or an editor can edit the event.
	 */
	public function test_edit_ownership(): void {
		$owner = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		$other = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		$event = wp_insert_post(
			[
				'post_type'   => 'reevents_event',
				'post_status' => 'pending',
				'post_title'  => 'Owned event',
				'post_author' => $owner,
			]
		);

		self::assertTrue( Submission_Handler::can_edit( $event, $owner ) );
		self::assertFalse( Submission_Handler::can_edit( $event, $other ) );
		self::assertFalse( Submission_Handler::can_edit( $event, 0 ) );
	}
}

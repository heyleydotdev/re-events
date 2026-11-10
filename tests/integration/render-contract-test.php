<?php
/**
 * Render contract integration tests.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Tests\Integration;

use ReEvents\Render\Events_List;
use WP_UnitTestCase;

/**
 * Covers widget text and class arguments.
 */
final class Render_Contract_Test extends WP_UnitTestCase {

	/**
	 * Empty state text comes from the widget args.
	 */
	public function test_empty_label_arg(): void {
		$html = Events_List::render(
			[
				'category'    => 999999,
				'empty_label' => 'Nothing scheduled.',
			]
		);

		self::assertStringContainsString( 'Nothing scheduled.', $html );
	}

	/**
	 * Wrapper and list classes come from the widget args.
	 */
	public function test_class_args(): void {
		$event = self::factory()->post->create(
			[
				'post_type'   => 'reevents_event',
				'post_status' => 'publish',
				'post_title'  => 'Class event',
			]
		);

		update_post_meta( $event, '_reevents_start_utc', gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ) );
		update_post_meta( $event, '_reevents_end_utc', gmdate( 'Y-m-d H:i:s', time() + 2 * DAY_IN_SECONDS ) );
		update_post_meta( $event, '_reevents_timezone', 'UTC' );

		$html = Events_List::render(
			[
				'class'         => 'my-list',
				'wrapper_class' => 'my-wrapper',
			]
		);

		self::assertStringContainsString( 'my-list', $html );
		self::assertStringContainsString( 'my-wrapper', $html );
	}
}

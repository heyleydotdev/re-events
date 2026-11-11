<?php
/**
 * Block renderer tests.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Tests\Integration;

use ReEvents\CLI\Test_Factory;
use ReEvents\Frontend\Blocks;
use WP_UnitTestCase;

/**
 * Covers the block render callbacks.
 */
final class Blocks_Test extends WP_UnitTestCase {

	/**
	 * Event used by the tests.
	 *
	 * @var int
	 */
	private int $event = 0;

	/**
	 * Create an event with a ticket.
	 */
	public function set_up(): void {
		parent::set_up();

		\ReEvents\Capabilities::add();
		delete_option( 'reevents_settings' );

		$admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin );

		$this->event = Test_Factory::event(
			[
				'title' => 'Block event',
				'meta'  => [
					'_reevents_start_utc' => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) ),
					'_reevents_end_utc'   => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day +1 hour' ) ),
					'_reevents_timezone'  => 'UTC',
				],
			]
		);
		Test_Factory::ticket( $this->event );
	}

	/**
	 * The field block renders a single value.
	 */
	public function test_render_field(): void {
		$html = Blocks::render_field(
			[
				'source' => 'event',
				'field'  => 'title',
				'id'     => $this->event,
				'label'  => 'Event:',
			]
		);

		self::assertStringContainsString( 'Block event', $html );
		self::assertStringContainsString( 'reevents-field-label', $html );
	}

	/**
	 * The field block supports venue values.
	 */
	public function test_render_venue_field(): void {
		$venue = wp_insert_post(
			[
				'post_type'   => 'reevents_venue',
				'post_status' => 'publish',
				'post_title'  => 'Block venue',
			]
		);
		update_post_meta( $venue, '_reevents_website', 'https://example.com/block' );

		self::assertStringContainsString(
			'https://example.com/block',
			Blocks::render_field(
				[
					'source' => 'venue',
					'field'  => 'website',
					'id'     => $venue,
				]
			)
		);
	}
}

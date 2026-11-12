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
	 * Every camelCase block attribute maps to a renderer argument.
	 */
	public function test_block_attributes_are_mapped(): void {
		$map    = Blocks::attribute_map();
		$blocks = [ 'events-list', 'event-details', 'venues-list', 'events-search', 'calendar', 'map' ];

		foreach ( $blocks as $block ) {
			$file = RE_EVENTS_DIR . 'blocks/src/' . $block . '/block.json';
			self::assertFileExists( $file );

			$data = wp_json_file_decode( $file, [ 'associative' => true ] );
			self::assertIsArray( $data );

			foreach ( array_keys( (array) ( $data['attributes'] ?? [] ) ) as $attribute ) {
				if ( preg_match( '/[A-Z]/', $attribute ) ) {
					self::assertArrayHasKey( $attribute, $map, $block . ':' . $attribute );
				}
			}
		}
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

	/**
	 * Empty block labels fall back to the translated defaults.
	 */
	public function test_booking_form_default_labels(): void {
		$html = Blocks::render_booking_form( [ 'event' => $this->event ] );

		self::assertStringContainsString( '<button type="submit">Book</button>', $html );
	}

	/**
	 * The booking form block renders the form.
	 */
	public function test_render_booking_form(): void {
		$html = Blocks::render_booking_form(
			[
				'event'          => $this->event,
				'buttonLabel'    => 'Reserve',
				'successMessage' => 'See you there.',
			]
		);

		self::assertStringContainsString( 'reevents-booking-form', $html );
		self::assertStringContainsString( 'Reserve', $html );
	}
}

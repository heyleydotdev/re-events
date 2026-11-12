<?php
/**
 * Event details rendering tests.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Tests\Integration;

use ReEvents\CLI\Test_Factory;
use ReEvents\Data\Ticket_Repository;
use ReEvents\Render\Event_Details;
use WP_UnitTestCase;

/**
 * Covers the event details renderer.
 */
final class Event_Details_Test extends WP_UnitTestCase {

	/**
	 * Default output keeps the title, date, venue and content.
	 */
	public function test_default_markup(): void {
		$venue = wp_insert_post(
			[
				'post_type'   => 'reevents_venue',
				'post_status' => 'publish',
				'post_title'  => 'Detail venue',
			]
		);

		$id = Test_Factory::event(
			[
				'title' => 'Detail event',
				'meta'  => [
					'_reevents_start_utc' => '2027-06-01 10:00:00',
					'_reevents_end_utc'   => '2027-06-01 11:00:00',
					'_reevents_timezone'  => 'UTC',
					'_reevents_venue_id'  => $venue,
				],
			]
		);

		$html = Event_Details::render( [ 'id' => $id ] );

		self::assertStringContainsString( '<h2 class="reevents-event-title">Detail event</h2>', $html );
		self::assertStringContainsString( 'Detail venue', $html );
		self::assertStringContainsString( 'reevents-event-when', $html );
		self::assertStringNotContainsString( 'reevents-event-image', $html );

		$without = Event_Details::render(
			[
				'id'                  => $id,
				'show_calendar_links' => false,
			]
		);

		self::assertStringNotContainsString( 'reevents-event-calendar-links', $without );
	}

	/**
	 * Card options add image, price, seats, terms, excerpt and links.
	 */
	public function test_options(): void {
		$venue = wp_insert_post(
			[
				'post_type'   => 'reevents_venue',
				'post_status' => 'publish',
				'post_title'  => 'Option venue',
			]
		);

		update_post_meta( $venue, '_reevents_address', '1 Test Street' );
		update_post_meta( $venue, '_reevents_lat', '51.5' );
		update_post_meta( $venue, '_reevents_lng', '-0.12' );

		$id = Test_Factory::event(
			[
				'title' => 'Option event',
				'meta'  => [
					'_reevents_start_utc' => '2027-06-01 10:00:00',
					'_reevents_end_utc'   => '2027-06-03 15:00:00',
					'_reevents_timezone'  => 'UTC',
					'_reevents_status'    => 'cancelled',
					'_reevents_venue_id'  => $venue,
				],
			]
		);

		wp_update_post(
			[
				'ID'           => $id,
				'post_excerpt' => 'Detail teaser',
			]
		);

		wp_set_object_terms( $id, [ 'Detail category' ], 'reevents_event_category' );
		wp_set_object_terms( $id, [ 'Detail tag' ], 'reevents_event_tag' );

		$ticket = Test_Factory::ticket(
			$id,
			[
				'capacity'    => 10,
				'price_minor' => 2500,
			]
		);
		( new Ticket_Repository() )->reserve( $ticket, 3 );

		$attachment = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg', $id );
		set_post_thumbnail( $id, $attachment );

		$html = Event_Details::render(
			[
				'id'                  => $id,
				'show_image'          => true,
				'show_title'          => true,
				'title_tag'           => 'h3',
				'show_status'         => true,
				'show_price'          => true,
				'show_seats_left'     => true,
				'show_categories'     => true,
				'show_tags'           => true,
				'show_excerpt'        => true,
				'show_venue_address'  => true,
				'show_venue_map_link' => true,
				'date_format'         => 'Y/m/d',
				'time_format'         => 'H:i',
			]
		);

		self::assertStringContainsString( 'reevents-event-thumbnail', $html );
		self::assertStringContainsString( '<h3 class="reevents-event-title">Option event</h3>', $html );
		self::assertStringContainsString( 'Cancelled', $html );
		self::assertStringContainsString( '2027/06/01 10:00', $html );
		self::assertStringContainsString( '2027/06/03 15:00', $html );
		self::assertStringContainsString( '25.00 USD', $html );
		self::assertStringContainsString( '7 seats left', $html );
		self::assertStringContainsString( 'Detail category', $html );
		self::assertStringContainsString( 'Detail tag', $html );
		self::assertStringContainsString( 'Detail teaser', $html );
		self::assertStringContainsString( '1 Test Street', $html );
		self::assertStringContainsString( 'openstreetmap.org', $html );
	}

	/**
	 * Toggles remove title, status and end date.
	 */
	public function test_toggles(): void {
		$id = Test_Factory::event(
			[
				'title' => 'Toggle event',
				'meta'  => [
					'_reevents_start_utc' => '2027-06-01 10:00:00',
					'_reevents_end_utc'   => '2027-06-03 15:00:00',
					'_reevents_timezone'  => 'UTC',
					'_reevents_status'    => 'cancelled',
				],
			]
		);

		$html = Event_Details::render(
			[
				'id'          => $id,
				'show_title'  => false,
				'show_status' => false,
				'show_end'    => false,
				'date_format' => 'Y/m/d',
				'time_format' => 'H:i',
			]
		);

		self::assertStringNotContainsString( 'reevents-event-title', $html );
		self::assertStringNotContainsString( 'Cancelled', $html );
		self::assertStringNotContainsString( '2027/06/03', $html );
	}
}

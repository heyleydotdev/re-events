<?php
/**
 * Venues list rendering tests.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Tests\Integration;

use ReEvents\CLI\Test_Factory;
use ReEvents\Render\Venues_List;
use ReEvents\Services\Occurrence_Service;
use WP_UnitTestCase;

/**
 * Covers the venues list renderer.
 */
final class Venues_List_Test extends WP_UnitTestCase {

	/**
	 * Create a venue.
	 *
	 * @param string               $title Venue title.
	 * @param array<string, mixed> $meta  Extra meta.
	 */
	private function venue( string $title, array $meta = [] ): int {
		$id = wp_insert_post(
			[
				'post_type'    => 'reevents_venue',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => 'one two three four five',
			]
		);

		foreach ( $meta as $key => $value ) {
			update_post_meta( $id, $key, $value );
		}

		return $id;
	}

	/**
	 * Search and order filter the venue query.
	 */
	public function test_search_and_order(): void {
		$this->venue( 'Alpha Hall' );
		$this->venue( 'Beta Hall' );

		$html = Venues_List::render(
			[
				'limit'  => 10,
				'search' => 'Alpha',
			]
		);

		self::assertStringContainsString( 'Alpha Hall', $html );
		self::assertStringNotContainsString( 'Beta Hall', $html );

		$html  = Venues_List::render(
			[
				'limit' => 10,
				'order' => 'desc',
			]
		);
		$beta  = strpos( $html, 'Beta Hall' );
		$alpha = strpos( $html, 'Alpha Hall' );

		self::assertNotFalse( $beta );
		self::assertNotFalse( $alpha );
		self::assertTrue( $beta < $alpha );
	}

	/**
	 * Upcoming filters venues and prints the next event.
	 */
	public function test_upcoming_and_next_event(): void {
		$inside  = $this->venue( 'Upcoming venue' );
		$outside = $this->venue( 'Quiet venue' );

		$event = Test_Factory::event(
			[
				'title' => 'Venue next event',
				'meta'  => [
					'_reevents_start_utc' => '2027-06-01 10:00:00',
					'_reevents_end_utc'   => '2027-06-01 11:00:00',
					'_reevents_timezone'  => 'UTC',
					'_reevents_status'    => 'scheduled',
					'_reevents_venue_id'  => $inside,
				],
			]
		);
		Occurrence_Service::sync( $event );

		$html = Venues_List::render(
			[
				'limit'           => 10,
				'upcoming'        => true,
				'show_next_event' => true,
				'date_format'     => 'Y/m/d',
				'time_format'     => 'H:i',
			]
		);

		self::assertStringContainsString( 'Upcoming venue', $html );
		self::assertStringNotContainsString( 'Quiet venue', $html );
		self::assertStringContainsString( 'Venue next event', $html );
		self::assertStringContainsString( '2027/06/01 10:00', $html );
	}

	/**
	 * Card options print image, address, links and a trimmed description.
	 */
	public function test_card_options(): void {
		$venue = $this->venue(
			'Card venue',
			[
				'_reevents_address' => '1 Test Street',
				'_reevents_website' => 'https://example.com/venue',
				'_reevents_lat'     => '51.5',
				'_reevents_lng'     => '-0.12',
			]
		);

		$attachment = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg', $venue );
		set_post_thumbnail( $venue, $attachment );

		$html = Venues_List::render(
			[
				'limit'          => 10,
				'search'         => 'Card venue',
				'show_image'     => true,
				'show_address'   => true,
				'show_website'   => true,
				'show_map_link'  => true,
				'excerpt_length' => 3,
				'title_tag'      => 'h3',
				'link'           => false,
			]
		);

		self::assertStringContainsString( 'reevents-venue-thumbnail', $html );
		self::assertStringContainsString( '<span class="reevents-venue-image"', $html );
		self::assertStringNotContainsString( '<a class="reevents-venue-image"', $html );
		self::assertStringContainsString( '1 Test Street', $html );
		self::assertStringContainsString( 'example.com/venue', $html );
		self::assertStringContainsString( 'openstreetmap.org', $html );
		self::assertStringContainsString( 'one two three', $html );
		self::assertStringNotContainsString( 'four', $html );
	}

	/**
	 * The query count does not grow with the number of cards.
	 */
	public function test_query_count_is_flat(): void {
		for ( $i = 1; $i <= 5; $i++ ) {
			$this->venue( 'Count venue ' . $i );
		}

		$before = get_num_queries();
		Venues_List::render(
			[
				'limit'           => 1,
				'show_next_event' => true,
			]
		);
		$one = get_num_queries() - $before;

		$before = get_num_queries();
		Venues_List::render(
			[
				'limit'           => 5,
				'show_next_event' => true,
			]
		);
		$many = get_num_queries() - $before;

		self::assertLessThanOrEqual( $one + 2, $many );
	}

	/**
	 * The default venue card keeps the description.
	 */
	public function test_default_description(): void {
		$this->venue( 'Default venue' );

		$html = Venues_List::render(
			[
				'limit'  => 10,
				'search' => 'Default venue',
			]
		);

		self::assertStringContainsString( 'one two three four five', $html );
	}
}

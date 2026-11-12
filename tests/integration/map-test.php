<?php
/**
 * Map renderer tests.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Tests\Integration;

use ReEvents\CLI\Test_Factory;
use ReEvents\Render\Map;
use ReEvents\Services\Occurrence_Service;
use WP_UnitTestCase;

/**
 * Covers the map fallback list.
 */
final class Map_Test extends WP_UnitTestCase {

	/**
	 * Filters and viewport arguments reach the map.
	 */
	public function test_filters_and_viewport(): void {
		$venue = wp_insert_post(
			[
				'post_type'   => 'reevents_venue',
				'post_status' => 'publish',
				'post_title'  => 'Alpha mapped venue',
			]
		);
		update_post_meta( $venue, '_reevents_lat', '40.7' );
		update_post_meta( $venue, '_reevents_lng', '-74.0' );
		update_post_meta( $venue, '_reevents_address', '1 Map Street' );

		$other = wp_insert_post(
			[
				'post_type'   => 'reevents_venue',
				'post_status' => 'publish',
				'post_title'  => 'Beta mapped venue',
			]
		);
		update_post_meta( $other, '_reevents_lat', '41.0' );
		update_post_meta( $other, '_reevents_lng', '-73.0' );

		$html = Map::render(
			[
				'limit'        => 10,
				'search'       => 'Alpha',
				'center'       => '40.7,-74.0',
				'zoom'         => 8,
				'fit'          => false,
				'link'         => false,
				'show_address' => true,
			]
		);

		self::assertStringContainsString( 'Alpha mapped venue', $html );
		self::assertStringNotContainsString( 'Beta mapped venue', $html );
		self::assertStringContainsString( 'data-center="40.7,-74"', $html );
		self::assertStringContainsString( 'data-zoom="8"', $html );
		self::assertStringContainsString( 'data-fit="0"', $html );
		self::assertStringContainsString( '1 Map Street', $html );

		$invalid = Map::render(
			[
				'limit'  => 10,
				'center' => '999,999',
				'zoom'   => 99,
			]
		);

		self::assertStringContainsString( 'data-center=""', $invalid );
		self::assertStringContainsString( 'data-zoom="19"', $invalid );
	}

	/**
	 * Upcoming filters mapped venues and markers can show the next event.
	 */
	public function test_upcoming_and_next_event(): void {
		$venue = wp_insert_post(
			[
				'post_type'   => 'reevents_venue',
				'post_status' => 'publish',
				'post_title'  => 'Upcoming mapped venue',
			]
		);
		update_post_meta( $venue, '_reevents_lat', '40.7' );
		update_post_meta( $venue, '_reevents_lng', '-74.0' );

		$quiet = wp_insert_post(
			[
				'post_type'   => 'reevents_venue',
				'post_status' => 'publish',
				'post_title'  => 'Quiet mapped venue',
			]
		);
		update_post_meta( $quiet, '_reevents_lat', '41.0' );
		update_post_meta( $quiet, '_reevents_lng', '-73.0' );

		$event = Test_Factory::event(
			[
				'title' => 'Map next event',
				'meta'  => [
					'_reevents_start_utc' => '2027-06-01 10:00:00',
					'_reevents_end_utc'   => '2027-06-01 11:00:00',
					'_reevents_timezone'  => 'UTC',
					'_reevents_status'    => 'scheduled',
					'_reevents_venue_id'  => $venue,
				],
			]
		);
		Occurrence_Service::sync( $event );

		$html = Map::render(
			[
				'limit'           => 10,
				'upcoming'        => true,
				'show_next_event' => true,
				'date_format'     => 'Y/m/d',
				'time_format'     => 'H:i',
			]
		);

		self::assertStringContainsString( 'Upcoming mapped venue', $html );
		self::assertStringNotContainsString( 'Quiet mapped venue', $html );
		self::assertStringContainsString( 'Map next event', $html );
		self::assertStringContainsString( '2027/06/01 10:00', $html );
	}

	/**
	 * The no-JS list includes mapped venues.
	 */
	public function test_noscript_list(): void {
		$venue = wp_insert_post(
			[
				'post_type'   => 'reevents_venue',
				'post_status' => 'publish',
				'post_title'  => 'Mapped venue',
			]
		);
		update_post_meta( $venue, '_reevents_lat', '40.7' );
		update_post_meta( $venue, '_reevents_lng', '-74.0' );

		$html = Map::render( [ 'limit' => 10 ] );

		self::assertStringContainsString( 'reevents-map-list', $html );
		self::assertStringContainsString( 'Mapped venue', $html );
		self::assertStringContainsString( 'data-fit="0"', $html );
		self::assertStringContainsString( esc_url( get_permalink( $venue ) ), $html );
	}
}

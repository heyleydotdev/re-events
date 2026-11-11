<?php
/**
 * Map renderer tests.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Tests\Integration;

use ReEvents\Render\Map;
use WP_UnitTestCase;

/**
 * Covers the map fallback list.
 */
final class Map_Test extends WP_UnitTestCase {

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
		self::assertStringContainsString( esc_url( get_permalink( $venue ) ), $html );
	}
}

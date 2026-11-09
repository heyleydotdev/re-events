<?php
/**
 * Map renderer.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Render;

/**
 * Renders a venue map with OpenStreetMap.
 */
final class Map {

	/**
	 * Render the map.
	 *
	 * @param array<string, mixed> $args Widget arguments.
	 */
	public static function render( array $args ): string {
		$venues  = get_posts(
			[
				'post_type'      => 'reevents_venue',
				'post_status'    => 'publish',
				'posts_per_page' => max( 1, (int) ( $args['limit'] ?? 50 ) ),
				'orderby'        => 'title',
				'order'          => 'ASC',
			]
		);
		$markers = [];

		foreach ( $venues as $venue ) {
			$lat = (string) get_post_meta( $venue->ID, '_reevents_lat', true );
			$lng = (string) get_post_meta( $venue->ID, '_reevents_lng', true );

			if ( '' === $lat || '' === $lng || ! is_numeric( $lat ) || ! is_numeric( $lng ) ) {
				continue;
			}

			$markers[] = [
				'lat'   => round( (float) $lat, 6 ),
				'lng'   => round( (float) $lng, 6 ),
				'title' => (string) $venue->post_title,
				'url'   => (string) get_permalink( $venue->ID ),
			];
		}

		if ( ! empty( $markers ) ) {
			wp_enqueue_style( 'leaflet', plugins_url( 'assets/vendor/leaflet/leaflet.css', RE_EVENTS_FILE ), [], '1.9.4' );
			wp_enqueue_script( 'leaflet', plugins_url( 'assets/vendor/leaflet/leaflet.js', RE_EVENTS_FILE ), [], '1.9.4', true );
			wp_enqueue_script(
				'reevents-map',
				plugins_url( 'assets/js/frontend-map.js', RE_EVENTS_FILE ),
				[ 'leaflet' ],
				RE_EVENTS_VERSION,
				true
			);
		}

		return Template::render(
			'map',
			[
				'markers' => $markers,
				'height'  => max( 200, (int) ( $args['height'] ?? 400 ) ),
				'args'    => $args,
			]
		);
	}
}

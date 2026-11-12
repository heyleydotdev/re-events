<?php
/**
 * Map renderer.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Render;

use ReEvents\Admin\Settings;
use ReEvents\Data\Occurrence_Repository;

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
		$query_args = [
			'post_type'      => 'reevents_venue',
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, (int) ( $args['limit'] ?? 50 ) ),
			'orderby'        => 'title',
			'order'          => 'ASC',
			's'              => sanitize_text_field( (string) ( $args['search'] ?? '' ) ),
		];

		if ( ! empty( $args['upcoming'] ) ) {
			$venue_ids              = ( new Occurrence_Repository() )->venue_ids_with_upcoming(
				current_time( 'mysql', true ),
				gmdate( 'Y-m-d H:i:s', strtotime( '+1 year' ) )
			);
			$query_args['post__in'] = $venue_ids ? $venue_ids : [ 0 ];
		}

		if ( ! empty( $args['venue'] ) ) {
			$single                 = [ (int) $args['venue'] ];
			$query_args['post__in'] = isset( $query_args['post__in'] )
				? array_values( array_intersect( $query_args['post__in'], $single ) )
				: $single;
			$query_args['post__in'] = $query_args['post__in'] ? $query_args['post__in'] : [ 0 ];
		}

		$venues      = get_posts( $query_args );
		$venue_ids   = array_map( static fn( $venue ): int => (int) $venue->ID, $venues );
		$show_next   = ! empty( $args['show_next_event'] );
		$date_fmt    = (string) ( $args['date_format'] ?? '' );
		$time_fmt    = (string) ( $args['time_format'] ?? '' );
		$next_events = [];

		if ( $venue_ids ) {
			_prime_post_caches( $venue_ids, false, true );
		}

		if ( $venue_ids && $show_next ) {
			$next_events = ( new Occurrence_Repository() )->next_events_for_venues( $venue_ids, current_time( 'mysql', true ) );
			$event_ids   = array_values( array_filter( array_map( static fn( $row ): int => (int) $row['event_id'], $next_events ) ) );

			if ( $event_ids ) {
				_prime_post_caches( $event_ids, false, false );
			}
		}

		$markers  = [];
		$provider = 'osm';
		$link     = ! isset( $args['link'] ) || ! empty( $args['link'] );

		foreach ( $venues as $venue ) {
			$lat = (string) get_post_meta( $venue->ID, '_reevents_lat', true );
			$lng = (string) get_post_meta( $venue->ID, '_reevents_lng', true );

			if ( '' === $lat || '' === $lng || ! is_numeric( $lat ) || ! is_numeric( $lng ) ) {
				continue;
			}

			$next_event = '';

			if ( $show_next && isset( $next_events[ (int) $venue->ID ] ) ) {
				$row        = $next_events[ (int) $venue->ID ];
				$next_event = trim(
					Event_Format::occurrence_start( $row, $date_fmt, $time_fmt ) . ' ' . (string) get_the_title( (int) $row['event_id'] )
				);
			}

			$markers[] = [
				'lat'     => round( (float) $lat, 6 ),
				'lng'     => round( (float) $lng, 6 ),
				'title'   => (string) $venue->post_title,
				'url'     => $link ? (string) get_permalink( $venue->ID ) : '',
				'address' => ! empty( $args['show_address'] ) ? (string) get_post_meta( $venue->ID, '_reevents_address', true ) : '',
				'next'    => $next_event,
			];
		}

		$center = self::center( (string) ( $args['center'] ?? '' ) );
		$zoom   = max( 0, min( 19, (int) ( $args['zoom'] ?? 0 ) ) );
		$fit    = ! empty( $args['fit'] );

		if ( ! empty( $markers ) ) {
			$requested = strtolower( (string) ( $args['provider'] ?? '' ) );
			$provider  = in_array( $requested, [ 'osm', 'google' ], true ) ? $requested : (string) Settings::get( 'map_provider', 'osm' );
			$key       = (string) Settings::get( 'map_api_key', '' );

			if ( 'google' === $provider && '' !== $key ) {
				wp_enqueue_script(
					'reevents-google-maps',
					add_query_arg(
						[
							'key'     => $key,
							'loading' => 'async',
						],
						'https://maps.googleapis.com/maps/api/js'
					),
					[],
					null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Third-party script.
					true
				);
				wp_enqueue_script(
					'reevents-map',
					plugins_url( 'assets/js/frontend-map-google.js', RE_EVENTS_FILE ),
					[ 'reevents-google-maps' ],
					RE_EVENTS_VERSION,
					true
				);
			} else {
				$provider = 'osm';
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
		}

		return Template::render(
			'map',
			[
				'markers'  => $markers,
				'provider' => $provider,
				'height'   => max( 200, (int) ( $args['height'] ?? 400 ) ),
				'center'   => $center,
				'zoom'     => $zoom,
				'fit'      => $fit,
				'args'     => $args,
			]
		);
	}

	/**
	 * Validate a lat,lng pair.
	 *
	 * @param string $value Raw center value.
	 */
	private static function center( string $value ): string {
		$parts = array_map( 'trim', explode( ',', $value ) );

		if ( 2 !== count( $parts ) || ! is_numeric( $parts[0] ) || ! is_numeric( $parts[1] ) ) {
			return '';
		}

		$lat = (float) $parts[0];
		$lng = (float) $parts[1];

		if ( abs( $lat ) > 90 || abs( $lng ) > 180 ) {
			return '';
		}

		return $lat . ',' . $lng;
	}
}

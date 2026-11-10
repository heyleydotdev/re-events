<?php
/**
 * Venue geocoding.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Services;

use ReEvents\Admin\Settings;

/**
 * Fills venue coordinates from the address with the Google Geocoding API.
 */
final class Geocoder {

	/**
	 * Hook into WordPress.
	 */
	public static function register(): void {
		add_action( 'save_post_reevents_venue', [ self::class, 'maybe_geocode' ], 20 );
	}

	/**
	 * Geocode a venue after it is saved.
	 *
	 * @param int $post_id Venue ID.
	 */
	public static function maybe_geocode( int $post_id ): void {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! apply_filters( 'reevents_geocode_venue', true, $post_id ) ) {
			return;
		}

		$key = (string) Settings::get( 'map_api_key', '' );

		if ( '' === $key ) {
			return;
		}

		$address = trim( (string) get_post_meta( $post_id, '_reevents_address', true ) );

		if ( '' === $address ) {
			return;
		}

		$lat = (string) get_post_meta( $post_id, '_reevents_lat', true );
		$lng = (string) get_post_meta( $post_id, '_reevents_lng', true );

		if ( '' !== $lat && '' !== $lng ) {
			return;
		}

		$coordinates = self::lookup( $address, $key );

		if ( ! $coordinates ) {
			return;
		}

		update_post_meta( $post_id, '_reevents_lat', $coordinates['lat'] );
		update_post_meta( $post_id, '_reevents_lng', $coordinates['lng'] );
	}

	/**
	 * Look up coordinates for an address.
	 *
	 * @param string $address Address.
	 * @param string $key     API key.
	 * @return array{lat:string,lng:string}|null
	 */
	public static function lookup( string $address, string $key ): ?array {
		$url = add_query_arg(
			[
				'address' => $address,
				'key'     => $key,
			],
			'https://maps.googleapis.com/maps/api/geocode/json'
		);

		$response = wp_remote_get( $url, [ 'timeout' => 5 ] );

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) || 'OK' !== ( $body['status'] ?? '' ) || empty( $body['results'][0]['geometry']['location'] ) ) {
			return null;
		}

		$location = $body['results'][0]['geometry']['location'];

		if ( ! isset( $location['lat'], $location['lng'] ) || ! is_numeric( $location['lat'] ) || ! is_numeric( $location['lng'] ) ) {
			return null;
		}

		return [
			'lat' => (string) round( (float) $location['lat'], 7 ),
			'lng' => (string) round( (float) $location['lng'], 7 ),
		];
	}
}

<?php
/**
 * Geocoding integration tests.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Tests\Integration;

use ReEvents\Data\Venue_Repository;
use ReEvents\Services\Geocoder;
use WP_UnitTestCase;

/**
 * Covers the Google geocoding response handling.
 */
final class Geocoder_Test extends WP_UnitTestCase {

	/**
	 * Saved settings.
	 *
	 * @var array<string, mixed>
	 */
	private array $settings = [];

	/**
	 * Mock the Google response and keep the settings.
	 */
	public function set_up(): void {
		parent::set_up();

		$settings       = get_option( 'reevents_settings', [] );
		$this->settings = is_array( $settings ) ? $settings : [];

		add_filter( 'pre_http_request', [ $this, 'response' ], 10, 3 );
	}

	/**
	 * Restore the settings.
	 */
	public function tear_down(): void {
		update_option( 'reevents_settings', $this->settings );

		parent::tear_down();
	}

	/**
	 * Return a fixed geocoding response.
	 *
	 * @param mixed                $preempt Whether to short-circuit.
	 * @param array<string, mixed> $args    Request arguments.
	 * @param string               $url     Request URL.
	 * @return mixed
	 */
	public function response( $preempt, array $args, string $url ) {
		unset( $args );

		if ( false === strpos( $url, 'maps.googleapis.com' ) ) {
			return $preempt;
		}

		return [
			'headers'  => [],
			'body'     => (string) wp_json_encode(
				[
					'status'  => 'OK',
					'results' => [
						[
							'geometry' => [
								'location' => [
									'lat' => 12.3456789,
									'lng' => 98.7654321,
								],
							],
						],
					],
				]
			),
			'response' => [
				'code'    => 200,
				'message' => 'OK',
			],
			'cookies'  => [],
			'filename' => null,
		];
	}

	/**
	 * A successful lookup returns rounded coordinates.
	 */
	public function test_lookup(): void {
		$result = Geocoder::lookup( '1 Main Street', 'key' );

		if ( ! is_array( $result ) ) {
			self::fail( 'Expected coordinates.' );
		}

		self::assertSame( '12.3456789', $result['lat'] );
		self::assertSame( '98.7654321', $result['lng'] );
	}

	/**
	 * Saving a venue without coordinates fills them.
	 */
	public function test_maybe_geocode(): void {
		$settings                = $this->settings;
		$settings['map_api_key'] = 'key';
		update_option( 'reevents_settings', $settings );

		$venue_id = ( new Venue_Repository() )->save(
			[
				'title'   => 'Geocoded venue',
				'address' => '1 Main Street',
			]
		);

		self::assertIsInt( $venue_id );

		if ( ! is_int( $venue_id ) ) {
			return;
		}

		Geocoder::maybe_geocode( $venue_id );

		self::assertSame( '12.3456789', get_post_meta( $venue_id, '_reevents_lat', true ) );
		self::assertSame( '98.7654321', get_post_meta( $venue_id, '_reevents_lng', true ) );
	}
}

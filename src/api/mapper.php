<?php
/**
 * Public API input mapping.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\API;

use ReEvents\Data\Meta;
use ReEvents\Domain\Event;
use ReEvents\Services\Currency;

/**
 * Sanitizes public API arrays into service payloads.
 */
final class Mapper {

	/**
	 * Map an event payload.
	 *
	 * @param array<string, mixed> $data     Input data.
	 * @param Event|null           $existing Existing event for partial updates.
	 */
	public static function event( array $data, ?Event $existing = null ): Event {
		$existing ??= new Event();

		return new Event(
			(int) ( $data['id'] ?? $existing->id ),
			self::string( $data, 'title', $existing->title, 'sanitize_text_field' ),
			self::string( $data, 'start_utc', $existing->start_utc, [ Meta::class, 'sanitize_datetime' ] ),
			self::string( $data, 'end_utc', $existing->end_utc, [ Meta::class, 'sanitize_datetime' ] ),
			self::string( $data, 'timezone', $existing->timezone, [ Meta::class, 'sanitize_timezone' ] ),
			self::boolean( $data, 'all_day', $existing->all_day ),
			self::string( $data, 'rrule', $existing->rrule, 'sanitize_text_field' ),
			self::list( $data, 'exdates', $existing->exdates, [ Meta::class, 'sanitize_string_array' ] ),
			self::list( $data, 'rdates', $existing->rdates, [ Meta::class, 'sanitize_string_array' ] ),
			array_key_exists( 'timeslots', $data ) ? Meta::sanitize_timeslots( $data['timeslots'] ) : $existing->timeslots,
			(int) ( $data['venue_id'] ?? $existing->venue_id ),
			array_key_exists( 'organizer_ids', $data ) ? Meta::sanitize_id_array( $data['organizer_ids'] ) : $existing->organizer_ids,
			(int) ( $data['capacity'] ?? $existing->capacity ),
			array_key_exists( 'online_urls', $data ) ? Meta::sanitize_urls( $data['online_urls'] ) : $existing->online_urls,
			self::status( (string) ( $data['status'] ?? $existing->status ) )
		);
	}

	/**
	 * Map a venue payload.
	 *
	 * @param array<string, mixed> $data     Input data.
	 * @param array<string, mixed> $existing Existing venue.
	 * @return array<string, mixed>
	 */
	public static function venue( array $data, array $existing = [] ): array {
		return [
			'id'          => (int) ( $data['id'] ?? $existing['id'] ?? 0 ),
			'title'       => self::string( $data, 'title', (string) ( $existing['title'] ?? '' ), 'sanitize_text_field' ),
			'description' => self::string( $data, 'description', (string) ( $existing['description'] ?? '' ), 'sanitize_textarea_field' ),
			'address'     => self::string( $data, 'address', (string) ( $existing['address'] ?? '' ), 'sanitize_textarea_field' ),
			'lat'         => array_key_exists( 'lat', $data ) ? Meta::sanitize_latitude( $data['lat'] ) : (string) ( $existing['lat'] ?? '' ),
			'lng'         => array_key_exists( 'lng', $data ) ? Meta::sanitize_longitude( $data['lng'] ) : (string) ( $existing['lng'] ?? '' ),
			'website'     => self::string( $data, 'website', (string) ( $existing['website'] ?? '' ), 'esc_url_raw' ),
		];
	}

	/**
	 * Map an organizer payload.
	 *
	 * @param array<string, mixed> $data     Input data.
	 * @param array<string, mixed> $existing Existing organizer.
	 * @return array<string, mixed>
	 */
	public static function organizer( array $data, array $existing = [] ): array {
		return [
			'id'          => (int) ( $data['id'] ?? $existing['id'] ?? 0 ),
			'title'       => self::string( $data, 'title', (string) ( $existing['title'] ?? '' ), 'sanitize_text_field' ),
			'description' => self::string( $data, 'description', (string) ( $existing['description'] ?? '' ), 'sanitize_textarea_field' ),
		];
	}

	/**
	 * Map a ticket payload.
	 *
	 * @param int                  $event_id Event ID.
	 * @param array<string, mixed> $data     Input data.
	 * @param array<string, mixed> $existing Existing ticket.
	 * @return array<string, mixed>
	 */
	public static function ticket( int $event_id, array $data, array $existing = [] ): array {
		$status = self::string( $data, 'status', (string) ( $existing['status'] ?? 'active' ), 'sanitize_key' );

		return [
			'id'              => (int) ( $data['id'] ?? $existing['id'] ?? 0 ),
			'event_id'        => $event_id,
			'name'            => self::string( $data, 'name', (string) ( $existing['name'] ?? '' ), 'sanitize_text_field' ),
			'description'     => self::string( $data, 'description', (string) ( $existing['description'] ?? '' ), 'sanitize_textarea_field' ),
			'price_minor'     => max( 0, (int) ( $data['price_minor'] ?? $existing['price_minor'] ?? 0 ) ),
			'currency'        => Currency::sanitize_code( (string) ( $data['currency'] ?? $existing['currency'] ?? 'USD' ) ),
			'capacity'        => max( 0, (int) ( $data['capacity'] ?? $existing['capacity'] ?? 0 ) ),
			'min_per_booking' => max( 1, (int) ( $data['min_per_booking'] ?? $existing['min_per_booking'] ?? 1 ) ),
			'max_per_booking' => max( 0, (int) ( $data['max_per_booking'] ?? $existing['max_per_booking'] ?? 0 ) ),
			'sale_start_utc'  => self::string( $data, 'sale_start_utc', (string) ( $existing['sale_start_utc'] ?? '' ), [ Meta::class, 'sanitize_datetime' ] ),
			'sale_end_utc'    => self::string( $data, 'sale_end_utc', (string) ( $existing['sale_end_utc'] ?? '' ), [ Meta::class, 'sanitize_datetime' ] ),
			'status'          => in_array( $status, [ 'active', 'inactive' ], true ) ? $status : 'active',
		];
	}

	/**
	 * Read a sanitized string.
	 *
	 * @param array<string, mixed> $data    Input data.
	 * @param string               $key     Key.
	 * @param string               $fallback Default.
	 * @param callable|string      $filter  Sanitizer.
	 */
	private static function string( array $data, string $key, string $fallback, $filter ): string {
		if ( ! array_key_exists( $key, $data ) ) {
			return $fallback;
		}

		return (string) call_user_func( $filter, $data[ $key ] );
	}

	/**
	 * Read a boolean.
	 *
	 * @param array<string, mixed> $data    Input data.
	 * @param string               $key     Key.
	 * @param bool                 $fallback Default.
	 */
	private static function boolean( array $data, string $key, bool $fallback ): bool {
		return array_key_exists( $key, $data ) ? (bool) $data[ $key ] : $fallback;
	}

	/**
	 * Read a sanitized list.
	 *
	 * @param array<string, mixed> $data    Input data.
	 * @param string               $key     Key.
	 * @param array<int, string>   $fallback Default.
	 * @param callable|string      $filter  Sanitizer.
	 * @return array<int, string>
	 */
	private static function list( array $data, string $key, array $fallback, $filter ): array {
		if ( ! array_key_exists( $key, $data ) ) {
			return $fallback;
		}

		$value = call_user_func( $filter, $data[ $key ] );

		return is_array( $value ) ? array_values( $value ) : [];
	}

	/**
	 * Normalize an event status.
	 *
	 * @param string $status Raw status.
	 */
	private static function status( string $status ): string {
		$status = sanitize_key( $status );

		return in_array( $status, [ 'scheduled', 'cancelled' ], true ) ? $status : 'scheduled';
	}
}

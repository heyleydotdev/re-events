<?php
/**
 * Post meta.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Data;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Registers event meta.
 */
final class Meta {

	/**
	 * Hook into WordPress.
	 */
	public static function register(): void {
		add_action( 'init', [ self::class, 'register_meta' ] );
	}

	/**
	 * Register event meta.
	 */
	public static function register_meta(): void {
		$auth = [ self::class, 'auth_edit_post' ];

		register_post_meta(
			'reevents_event',
			'_reevents_start_utc',
			[
				'type'              => 'string',
				'description'       => __( 'Start time in UTC.', 're-events' ),
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => [ self::class, 'sanitize_datetime' ],
				'auth_callback'     => $auth,
			]
		);

		register_post_meta(
			'reevents_event',
			'_reevents_end_utc',
			[
				'type'              => 'string',
				'description'       => __( 'End time in UTC.', 're-events' ),
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => [ self::class, 'sanitize_datetime' ],
				'auth_callback'     => $auth,
			]
		);

		register_post_meta(
			'reevents_event',
			'_reevents_timezone',
			[
				'type'              => 'string',
				'description'       => __( 'Event timezone.', 're-events' ),
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => [ self::class, 'sanitize_timezone' ],
				'auth_callback'     => $auth,
			]
		);

		register_post_meta(
			'reevents_event',
			'_reevents_all_day',
			[
				'type'              => 'boolean',
				'description'       => __( 'Whether the event lasts all day.', 're-events' ),
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'auth_callback'     => $auth,
			]
		);

		register_post_meta(
			'reevents_event',
			'_reevents_rrule',
			[
				'type'              => 'string',
				'description'       => __( 'Recurrence rule.', 're-events' ),
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => $auth,
			]
		);

		register_post_meta(
			'reevents_event',
			'_reevents_exdates',
			[
				'type'              => 'array',
				'description'       => __( 'Excluded occurrence dates.', 're-events' ),
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => [ self::class, 'sanitize_string_array' ],
				'auth_callback'     => $auth,
			]
		);

		register_post_meta(
			'reevents_event',
			'_reevents_rdates',
			[
				'type'              => 'array',
				'description'       => __( 'Additional occurrence dates.', 're-events' ),
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => [ self::class, 'sanitize_string_array' ],
				'auth_callback'     => $auth,
			]
		);

		register_post_meta(
			'reevents_event',
			'_reevents_timeslots',
			[
				'type'              => 'array',
				'description'       => __( 'Daily timeslots.', 're-events' ),
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => [ self::class, 'sanitize_timeslots' ],
				'auth_callback'     => $auth,
			]
		);

		register_post_meta(
			'reevents_event',
			'_reevents_venue_id',
			[
				'type'              => 'integer',
				'description'       => __( 'Venue post ID.', 're-events' ),
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'absint',
				'auth_callback'     => $auth,
			]
		);

		register_post_meta(
			'reevents_event',
			'_reevents_organizer_ids',
			[
				'type'              => 'array',
				'description'       => __( 'Organizer post IDs.', 're-events' ),
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => [ self::class, 'sanitize_id_array' ],
				'auth_callback'     => $auth,
			]
		);

		register_post_meta(
			'reevents_event',
			'_reevents_capacity',
			[
				'type'              => 'integer',
				'description'       => __( 'Event capacity.', 're-events' ),
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'absint',
				'auth_callback'     => $auth,
			]
		);

		register_post_meta(
			'reevents_event',
			'_reevents_online_urls',
			[
				'type'              => 'array',
				'description'       => __( 'Online event links.', 're-events' ),
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => [ self::class, 'sanitize_urls' ],
				'auth_callback'     => $auth,
			]
		);

		register_post_meta(
			'reevents_event',
			'_reevents_status',
			[
				'type'              => 'string',
				'description'       => __( 'Event status.', 're-events' ),
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_key',
				'auth_callback'     => $auth,
			]
		);

		register_post_meta(
			'reevents_venue',
			'_reevents_address',
			[
				'type'              => 'string',
				'description'       => __( 'Venue address.', 're-events' ),
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_textarea_field',
				'auth_callback'     => $auth,
			]
		);

		register_post_meta(
			'reevents_venue',
			'_reevents_lat',
			[
				'type'              => 'string',
				'description'       => __( 'Venue latitude.', 're-events' ),
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => [ self::class, 'sanitize_latitude' ],
				'auth_callback'     => $auth,
			]
		);

		register_post_meta(
			'reevents_venue',
			'_reevents_lng',
			[
				'type'              => 'string',
				'description'       => __( 'Venue longitude.', 're-events' ),
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => [ self::class, 'sanitize_longitude' ],
				'auth_callback'     => $auth,
			]
		);

		register_post_meta(
			'reevents_venue',
			'_reevents_website',
			[
				'type'              => 'string',
				'description'       => __( 'Venue website.', 're-events' ),
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'esc_url_raw',
				'auth_callback'     => $auth,
			]
		);
	}

	/**
	 * Check whether the current user may edit the post.
	 *
	 * @param bool   $allowed  Whether the user can add the meta.
	 * @param string $meta_key Meta key.
	 * @param int    $post_id  Post ID.
	 * @return bool
	 */
	public static function auth_edit_post( $allowed, $meta_key, $post_id ): bool {
		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Sanitize a UTC datetime.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_datetime( $value ): string {
		$value = sanitize_text_field( (string) $value );
		$date  = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $value, new DateTimeZone( 'UTC' ) );

		return $date instanceof DateTimeImmutable ? $date->format( 'Y-m-d H:i:s' ) : '';
	}

	/**
	 * Sanitize an IANA timezone.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_timezone( $value ): string {
		$value = sanitize_text_field( (string) $value );

		return in_array( $value, timezone_identifiers_list(), true ) ? $value : 'UTC';
	}

	/**
	 * Sanitize an array of strings.
	 *
	 * @param mixed $value Raw value.
	 * @return string[]
	 */
	public static function sanitize_string_array( $value ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}

		return array_values(
			array_filter(
				array_map(
					static function ( $item ): string {
						return sanitize_text_field( (string) $item );
					},
					$value
				)
			)
		);
	}

	/**
	 * Sanitize an array of post IDs.
	 *
	 * @param mixed $value Raw value.
	 * @return int[]
	 */
	public static function sanitize_id_array( $value ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}

		return array_values(
			array_unique(
				array_map(
					static function ( $item ): int {
						return absint( $item );
					},
					$value
				)
			)
		);
	}

	/**
	 * Sanitize a list of labelled URLs.
	 *
	 * @param mixed $value Raw value.
	 * @return array<int, array{label:string,url:string}>
	 */
	public static function sanitize_urls( $value ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}

		$urls = [];

		foreach ( $value as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$url = esc_url_raw( (string) ( $item['url'] ?? '' ) );

			if ( '' === $url ) {
				continue;
			}

			$urls[] = [
				'label' => sanitize_text_field( (string) ( $item['label'] ?? '' ) ),
				'url'   => $url,
			];
		}

		return $urls;
	}

	/**
	 * Sanitize timeslots.
	 *
	 * @param mixed $value Raw value.
	 * @return array<int, array{start:string,end:string,capacity:int}>
	 */
	public static function sanitize_timeslots( $value ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}

		$slots = [];

		foreach ( $value as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$start = self::sanitize_time( (string) ( $item['start'] ?? '' ) );
			$end   = self::sanitize_time( (string) ( $item['end'] ?? '' ) );

			if ( '' === $start || '' === $end ) {
				continue;
			}

			$slots[] = [
				'start'    => $start,
				'end'      => $end,
				'capacity' => absint( $item['capacity'] ?? 0 ),
			];
		}

		return $slots;
	}

	/**
	 * Sanitize a latitude.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_latitude( $value ): string {
		return self::sanitize_coordinate( $value, -90, 90 );
	}

	/**
	 * Sanitize a longitude.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_longitude( $value ): string {
		return self::sanitize_coordinate( $value, -180, 180 );
	}

	/**
	 * Sanitize a coordinate.
	 *
	 * @param mixed $value Raw value.
	 * @param float $min   Minimum.
	 * @param float $max   Maximum.
	 */
	private static function sanitize_coordinate( $value, float $min, float $max ): string {
		$value = trim( sanitize_text_field( (string) $value ) );

		if ( '' === $value || ! is_numeric( $value ) ) {
			return '';
		}

		$number = (float) $value;

		return $number >= $min && $number <= $max ? (string) $number : '';
	}

	/**
	 * Sanitize an H:i time.
	 *
	 * @param string $value Raw time.
	 */
	private static function sanitize_time( string $value ): string {
		$time = DateTimeImmutable::createFromFormat( '!H:i', $value, new DateTimeZone( 'UTC' ) );

		return $time instanceof DateTimeImmutable ? $time->format( 'H:i' ) : '';
	}
}

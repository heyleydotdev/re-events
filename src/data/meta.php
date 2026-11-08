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
			're_event',
			'_re_start_utc',
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
			're_event',
			'_re_end_utc',
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
			're_event',
			'_re_timezone',
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
			're_event',
			'_re_all_day',
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
			're_event',
			'_re_rrule',
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
			're_event',
			'_re_exdates',
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
			're_event',
			'_re_venue_id',
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
			're_event',
			'_re_organizer_ids',
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
			're_event',
			'_re_capacity',
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
			're_event',
			'_re_online_url',
			[
				'type'              => 'string',
				'description'       => __( 'Online event URL.', 're-events' ),
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'esc_url_raw',
				'auth_callback'     => $auth,
			]
		);

		register_post_meta(
			're_event',
			'_re_status',
			[
				'type'              => 'string',
				'description'       => __( 'Event status.', 're-events' ),
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_key',
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
}

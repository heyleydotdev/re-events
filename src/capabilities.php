<?php
/**
 * Capabilities.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents;

/**
 * Registers plugin capabilities.
 */
final class Capabilities {

	/**
	 * Capabilities granted to administrators.
	 *
	 * @return string[]
	 */
	public static function all(): array {
		return array_merge(
			self::post_type_caps( 're_event' ),
			self::post_type_caps( 're_venue' ),
			self::post_type_caps( 're_organizer' ),
			[
				're_events_manage_bookings',
				're_events_manage_settings',
			]
		);
	}

	/**
	 * Grant capabilities to administrators.
	 */
	public static function add(): void {
		$role = get_role( 'administrator' );

		if ( ! $role ) {
			return;
		}

		foreach ( self::all() as $capability ) {
			$role->add_cap( $capability );
		}
	}

	/**
	 * Build the capabilities for a post type.
	 *
	 * @param string $singular Singular post type name.
	 * @return string[]
	 */
	private static function post_type_caps( string $singular ): array {
		$plural = $singular . 's';

		return [
			"edit_{$plural}",
			"edit_others_{$plural}",
			"edit_published_{$plural}",
			"edit_private_{$plural}",
			"publish_{$plural}",
			"read_private_{$plural}",
			"delete_{$plural}",
			"delete_others_{$plural}",
			"delete_published_{$plural}",
			"delete_private_{$plural}",
		];
	}
}

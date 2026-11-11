<?php
/**
 * Builder control schema.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Builders;

/**
 * Turns widget arguments into builder control definitions.
 */
final class Controls {

	/**
	 * Normalize a widget schema.
	 *
	 * @param array<string, mixed> $widget Widget registry entry.
	 * @return array<string, array{type:string,label:string,description:string,default:mixed}>
	 */
	public static function for_widget( array $widget ): array {
		$controls = [];

		foreach ( (array) ( $widget['args'] ?? [] ) as $key => $arg ) {
			if ( ! is_array( $arg ) ) {
				continue;
			}

			$controls[ (string) $key ] = [
				'type'        => self::type( (string) ( $arg['type'] ?? 'text' ) ),
				'label'       => self::label( (string) $key ),
				'description' => (string) ( $arg['description'] ?? '' ),
				'default'     => $arg['default'] ?? '',
			];
		}

		return $controls;
	}

	/**
	 * Build a readable label from an argument key.
	 *
	 * @param string $key Argument key.
	 */
	public static function label( string $key ): string {
		return ucwords( str_replace( [ '-', '_' ], ' ', $key ) );
	}

	/**
	 * Normalize an argument type.
	 *
	 * @param string $type Schema type.
	 */
	public static function type( string $type ): string {
		return in_array( $type, [ 'text', 'textarea', 'number', 'toggle', 'select', 'color', 'date' ], true ) ? $type : 'text';
	}
}

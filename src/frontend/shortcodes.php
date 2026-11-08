<?php
/**
 * Shortcodes.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Frontend;

use ReEvents\Render\Widget_Registry;

/**
 * Registers shortcodes from the widget registry.
 */
final class Shortcodes {

	/**
	 * Hook into WordPress.
	 */
	public static function register(): void {
		foreach ( Widget_Registry::all() as $id => $widget ) {
			if ( empty( $widget['shortcode'] ) ) {
				continue;
			}

			add_shortcode(
				(string) $widget['shortcode'],
				static function ( array $atts ) use ( $id ): string {
					return Widget_Registry::render( $id, $atts );
				}
			);
		}
	}
}

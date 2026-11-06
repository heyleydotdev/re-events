<?php
/**
 * Admin view renderer.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Admin;

use League\Plates\Engine;

/**
 * Renders the admin templates.
 */
final class View {

	/**
	 * Plates engine.
	 *
	 * @var Engine|null
	 */
	private static ?Engine $engine = null;

	/**
	 * Renders a template.
	 *
	 * @param string               $template Template path without extension.
	 * @param array<string, mixed> $data     Template data.
	 */
	public static function render( string $template, array $data = array() ): string {
		if ( null === self::$engine ) {
			self::$engine = new Engine( (string) constant( 'REEVENTS_PATH' ) . 'src/admin/views' );
		}

		return self::$engine->render( $template, $data );
	}
}

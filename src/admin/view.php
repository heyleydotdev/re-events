<?php
/**
 * Admin view renderer.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Admin;

use League\Plates\Engine;
use League\Plates\Exception\TemplateNotFound;

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

		try {
			return self::$engine->render( $template, $data );
		} catch ( TemplateNotFound ) {
			$reevents_message = sprintf(
				/* translators: %s: template name. */
				__( 'ReEvents admin view is missing: %s', 're-events' ),
				$template
			);

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
				error_log( $reevents_message );
			}

			return '<div class="notice notice-error"><p>' . esc_html( $reevents_message ) . '</p></div>';
		}
	}
}

<?php
/**
 * Template loader.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Render;

/**
 * Locates and renders templates with theme overrides.
 */
final class Template {

	// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $args is used by the included template.
	/**
	 * Render a template.
	 *
	 * @param string               $name Template name without extension.
	 * @param array<string, mixed> $args Template arguments.
	 */
	public static function render( string $name, array $args = [] ): string {
		$file = self::locate( $name );

		if ( '' === $file ) {
			return '';
		}

		ob_start();
		include $file;

		return (string) ob_get_clean();
	}
	// phpcs:enable

	/**
	 * Locate a template, preferring the theme.
	 *
	 * @param string $name Template name without extension.
	 */
	public static function locate( string $name ): string {
		$name = str_replace( [ '..', '\\', "\0" ], '', $name );
		$name = trim( preg_replace( '/\.php$/', '', $name ) ?? '', '/' );
		$name = preg_replace( '/[^a-zA-Z0-9_\-\/]/', '', $name ) ?? '';

		if ( '' === $name ) {
			return '';
		}

		$theme = locate_template( [ 're-events/' . $name . '.php' ] );

		if ( $theme ) {
			return $theme;
		}

		$plugin = RE_EVENTS_DIR . 'templates/' . $name . '.php';

		return is_readable( $plugin ) ? $plugin : '';
	}
}

<?php
/**
 * Shortcode documentation generator.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\CLI;

use ReEvents\Render\Widget_Registry;
use WP_CLI;

/**
 * Builds the Docsify shortcode reference from the widget registry.
 */
final class Shortcode_Docs {

	/**
	 * Print the Markdown reference.
	 *
	 * ## EXAMPLES
	 *
	 *     wp re-events shortcode-docs > docs/shortcodes/README.md
	 */
	public static function run(): void {
		WP_CLI::line( self::build() );
	}

	/**
	 * Build the Markdown.
	 */
	private static function build(): string {
		$lines = [
			'# ReEvents shortcodes',
			'',
			'Every registered widget is listed here. Arguments left at their default can be omitted.',
			'',
		];

		foreach ( Widget_Registry::all() as $widget ) {
			if ( empty( $widget['shortcode'] ) ) {
				continue;
			}

			$shortcode = (string) $widget['shortcode'];
			$label     = (string) ( $widget['label'] ?? $shortcode );

			$lines[] = '## ' . $label;
			$lines[] = '';
			$lines[] = '`[' . $shortcode . ']`';
			$lines[] = '';
			$lines[] = '| Argument | Default | Description |';
			$lines[] = '| --- | --- | --- |';

			$example = '[' . $shortcode;

			foreach ( (array) ( $widget['args'] ?? [] ) as $key => $arg ) {
				if ( ! is_array( $arg ) ) {
					continue;
				}

				$default = self::default_text( $arg['default'] ?? '' );
				$display = '' === $default ? '—' : $default;

				$lines[] = '| `' . self::cell( (string) $key ) . '` | ' . self::cell( $display ) . ' | ' . self::cell( (string) ( $arg['description'] ?? '' ) ) . ' |';

				if ( '' !== $default && '0' !== $default && 'false' !== $default ) {
					$example .= ' ' . $key . '="' . self::attribute( $default ) . '"';
				}
			}

			$example .= ']';

			$lines[] = '';
			$lines[] = '```text';
			$lines[] = $example;
			$lines[] = '```';
			$lines[] = '';
		}

		return implode( "\n", $lines );
	}

	/**
	 * Convert a default value to text.
	 *
	 * @param mixed $value Default value.
	 */
	private static function default_text( $value ): string {
		if ( is_bool( $value ) ) {
			return $value ? '1' : '0';
		}

		if ( is_array( $value ) ) {
			return implode( ', ', array_map( 'strval', $value ) );
		}

		return (string) $value;
	}

	/**
	 * Escape a Markdown table cell.
	 *
	 * @param string $value Cell value.
	 */
	private static function cell( string $value ): string {
		return str_replace( '|', '\\|', $value );
	}

	/**
	 * Escape a shortcode attribute value.
	 *
	 * @param string $value Value.
	 */
	private static function attribute( string $value ): string {
		return str_replace( '"', '&quot;', $value );
	}
}

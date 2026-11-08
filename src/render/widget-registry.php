<?php
/**
 * Widget registry.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Render;

/**
 * Single registry for renderers, shortcodes and builder widgets.
 */
final class Widget_Registry {

	/**
	 * Registered widgets.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private static array $widgets = [];

	/**
	 * Register the default widgets.
	 */
	public static function register_defaults(): void {
		self::register(
			'events-list',
			[
				'label'     => __( 'Events List', 're-events' ),
				'shortcode' => 'reevents_list',
				'renderer'  => [ Events_List::class, 'render' ],
				'args'      => [
					'limit'      => [
						'type'    => 'number',
						'default' => 5,
					],
					'category'   => [
						'type'    => 'text',
						'default' => '',
					],
					'venue'      => [
						'type'    => 'number',
						'default' => 0,
					],
					'upcoming'   => [
						'type'    => 'toggle',
						'default' => true,
					],
					'show_venue' => [
						'type'    => 'toggle',
						'default' => true,
					],
				],
			]
		);
	}

	/**
	 * Register a widget.
	 *
	 * @param string               $id     Widget ID.
	 * @param array<string, mixed> $widget Widget definition.
	 */
	public static function register( string $id, array $widget ): void {
		self::$widgets[ $id ] = $widget;
	}

	/**
	 * Get all widgets.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function all(): array {
		return self::$widgets;
	}

	/**
	 * Get a widget.
	 *
	 * @param string $id Widget ID.
	 * @return array<string, mixed>|null
	 */
	public static function get( string $id ): ?array {
		return self::$widgets[ $id ] ?? null;
	}

	/**
	 * Render a widget.
	 *
	 * @param string               $id   Widget ID.
	 * @param array<string, mixed> $args Widget arguments.
	 */
	public static function render( string $id, array $args = [] ): string {
		$widget = self::get( $id );

		if ( ! $widget ) {
			return '';
		}

		$args     = self::merge_args( (array) ( $widget['args'] ?? [] ), $args );
		$renderer = $widget['renderer'] ?? null;

		return is_callable( $renderer ) ? (string) call_user_func( $renderer, $args ) : '';
	}

	/**
	 * Merge arguments with the widget schema defaults.
	 *
	 * @param array<string, array<string, mixed>> $schema Argument schema.
	 * @param array<string, mixed>                $args   User arguments.
	 * @return array<string, mixed>
	 */
	private static function merge_args( array $schema, array $args ): array {
		$merged = [];

		foreach ( $schema as $key => $definition ) {
			$merged[ $key ] = array_key_exists( $key, $args ) ? $args[ $key ] : ( $definition['default'] ?? null );
		}

		return $merged;
	}
}

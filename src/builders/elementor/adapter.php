<?php
/**
 * Elementor adapter.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Builders\Elementor;

use ReEvents\Builders\Builder_Adapter;
use ReEvents\Render\Widget_Registry;

/**
 * Registers ReEvents widgets in Elementor.
 */
final class Adapter implements Builder_Adapter {

	/**
	 * Adapter ID.
	 */
	public static function id(): string {
		return 'elementor';
	}

	/**
	 * Builder label.
	 */
	public static function label(): string {
		return 'Elementor';
	}

	/**
	 * Whether Elementor is loaded.
	 */
	public static function is_active(): bool {
		return defined( 'ELEMENTOR_VERSION' );
	}

	/**
	 * Hook into Elementor.
	 */
	public static function register(): void {
		add_action( 'elementor/elements/categories_registered', [ self::class, 'register_category' ] );
		add_action( 'elementor/widgets/register', [ self::class, 'register_widgets' ] );
	}

	/**
	 * Add the ReEvents widget category.
	 *
	 * @param \Elementor\Elements_Manager $elements_manager Elementor elements manager.
	 */
	public static function register_category( $elements_manager ): void {
		$elements_manager->add_category(
			'reevents',
			[
				'title' => __( 'ReEvents', 're-events' ),
				'icon'  => 'eicon-calendar',
			]
		);
	}

	/**
	 * Register every registry widget.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
	 */
	public static function register_widgets( $widgets_manager ): void {
		foreach ( self::widgets() as $class ) {
			$widgets_manager->register( new $class() );
		}
	}

	/**
	 * Widget classes in registry order.
	 *
	 * @return array<int, class-string<Widget>>
	 */
	public static function widgets(): array {
		$classes = [];

		foreach ( array_keys( Widget_Registry::all() ) as $id ) {
			$class = self::class_for( (string) $id );

			if ( class_exists( $class ) ) {
				$classes[] = $class;
			}
		}

		return $classes;
	}

	/**
	 * Map a registry ID to a widget class.
	 *
	 * @param string $id Registry ID.
	 */
	public static function class_for( string $id ): string {
		$name = str_replace( ' ', '_', ucwords( str_replace( '-', ' ', $id ) ) );

		return __NAMESPACE__ . '\\' . $name;
	}
}

<?php
/**
 * Page builder adapters.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Builders;

use ReEvents\Builders\Elementor\Adapter as Elementor;

/**
 * Registers the page builder adapters.
 */
final class Builders {

	/**
	 * Known adapters.
	 *
	 * @return array<int, class-string<Builder_Adapter>>
	 */
	public static function adapters(): array {
		return [
			Elementor::class,
		];
	}

	/**
	 * Hook into WordPress.
	 */
	public static function register(): void {
		foreach ( self::adapters() as $adapter ) {
			$adapter::register();
		}
	}

	/**
	 * IDs of the builders that are active on this request.
	 *
	 * @return array<int, string>
	 */
	public static function active(): array {
		$active = [];

		foreach ( self::adapters() as $adapter ) {
			if ( $adapter::is_active() ) {
				$active[] = $adapter::id();
			}
		}

		return $active;
	}
}

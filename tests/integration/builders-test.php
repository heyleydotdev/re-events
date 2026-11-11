<?php
/**
 * Builder adapter tests.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Tests\Integration;

use ReEvents\Builders\Elementor\Adapter;
use ReEvents\Render\Widget_Registry;
use WP_UnitTestCase;

/**
 * Covers the builder adapter mapping.
 */
final class Builders_Test extends WP_UnitTestCase {

	/**
	 * Every registry widget resolves to an Elementor class file.
	 */
	public function test_elementor_class_files(): void {
		$widgets = Widget_Registry::all();

		self::assertNotEmpty( $widgets );

		$classes = [];

		foreach ( array_keys( $widgets ) as $id ) {
			$class = Adapter::class_for( (string) $id );
			$name  = (string) substr( strrchr( $class, '\\' ), 1 );
			$file  = RE_EVENTS_DIR . 'src/builders/elementor/' . $id . '.php';

			self::assertFileExists( $file );

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a plugin file in a test.
			self::assertStringContainsString( 'class ' . $name . ' ', (string) file_get_contents( $file ) );

			$classes[] = $class;
		}

		self::assertSame( $classes, array_values( array_unique( $classes ) ) );
	}
}

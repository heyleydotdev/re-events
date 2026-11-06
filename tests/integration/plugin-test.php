<?php
/**
 * Plugin bootstrap smoke test.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Tests\Integration;

use ReEvents\Plugin;
use WP_UnitTestCase;

/**
 * Verifies that the plugin boots inside WordPress.
 */
final class Plugin_Test extends WP_UnitTestCase {

	/**
	 * The plugin constants and shared instance are available.
	 */
	public function test_plugin_bootstrap_loads(): void {
		$this->assertTrue( defined( 'REEVENTS_VERSION' ) );
		$this->assertSame( '0.1.0', REEVENTS_VERSION );
		$this->assertTrue( class_exists( Plugin::class ) );
	}
}

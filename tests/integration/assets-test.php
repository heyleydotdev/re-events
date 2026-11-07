<?php
/**
 * Admin assets test.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Tests\Integration;

use ReEvents\Admin\Assets;
use ReEvents\Admin\Menu;
use WP_UnitTestCase;

/**
 * Verifies admin asset loading.
 */
final class Assets_Test extends WP_UnitTestCase {

	/**
	 * Resets the current user between tests.
	 */
	protected function tearDown(): void {
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * The production font CSS never swaps.
	 */
	public function test_font_css_never_swaps(): void {
		$reevents_assets = new Assets();
		$reevents_method = new \ReflectionMethod( $reevents_assets, 'inline_fonts_css' );
		$reevents_method->setAccessible( true );
		$reevents_css = $reevents_method->invoke( $reevents_assets );

		if ( null === $reevents_css ) {
			$this->fail( 'Build assets are missing. Run bun run build.' );
		}

		$this->assertStringContainsString( '@font-face', $reevents_css );
		$this->assertStringContainsString( 'font-display: optional', $reevents_css );
		$this->assertStringNotContainsString( 'font-display:swap', $reevents_css );
		$this->assertStringNotContainsString( 'font-display: swap', $reevents_css );
	}

	/**
	 * Tailwind property registrations are emitted for the document.
	 */
	public function test_property_css_is_available(): void {
		$reevents_assets = new Assets();
		$reevents_method = new \ReflectionMethod( $reevents_assets, 'property_css' );
		$reevents_method->setAccessible( true );
		$reevents_css = $reevents_method->invoke( $reevents_assets );

		if ( null === $reevents_css ) {
			$this->fail( 'Build assets are missing. Run bun run build.' );
		}

		$this->assertStringContainsString( '@property --tw-border-style', $reevents_css );
	}

	/**
	 * A submenu screen enqueues the admin script.
	 */
	public function test_submenu_enqueues_script(): void {
		$reevents_assets = new Assets();

		if ( null === $reevents_assets->inline_css() ) {
			$this->fail( 'Build assets are missing. Run bun run build.' );
		}

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Reset core menu state for the test.
		$GLOBALS['menu'] = array();
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Reset core submenu state for the test.
		$GLOBALS['submenu'] = array();

		( new Menu() )->add_menu();

		$reevents_hook = get_plugin_page_hookname( Menu::SLUG . '-categories', Menu::SLUG );

		$this->assertIsString( $reevents_hook );

		$reevents_assets->enqueue( $reevents_hook );

		$this->assertTrue( wp_script_is( Assets::HANDLE, 'enqueued' ) );
	}
}

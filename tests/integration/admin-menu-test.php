<?php
/**
 * Admin menu test.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Tests\Integration;

use ReEvents\Admin\Assets;
use ReEvents\Admin\Menu;
use WP_UnitTestCase;

/**
 * Verifies the top-level ReEvents menu.
 */
final class Admin_Menu_Test extends WP_UnitTestCase {

	/**
	 * The menu is registered with the expected slug.
	 */
	public function test_menu_is_registered(): void {
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Reset core menu state for the test.
		$GLOBALS['menu'] = array();

		( new Menu() )->register();

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook.
		do_action( 'admin_menu' );

		$slugs = array_column( $GLOBALS['menu'], 2 );

		$this->assertContains( Menu::SLUG, $slugs );
	}

	/**
	 * The dashboard renders inside the base layout.
	 */
	public function test_dashboard_uses_base_layout(): void {
		ob_start();
		( new Menu() )->render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'id="re-events-host"', $html );
		$this->assertStringContainsString( 'view=events', $html );
		$this->assertStringContainsString( 'view=categories', $html );
		$this->assertStringContainsString( 'view=taxonomies', $html );
		$this->assertStringContainsString( 'view=locations', $html );
		$this->assertStringContainsString( 'view=bookings', $html );
		$this->assertStringNotContainsString( 'href="#"', $html );
		$this->assertStringContainsString( 'nav-link active', $html );
		$this->assertStringContainsString( 'aria-current="page"', $html );
		$this->assertStringContainsString( 'id="re-events-template"', $html );
		$this->assertStringContainsString( 'id="re-events-loading"', $html );

		$app_css = ( new Assets() )->inline_css();

		if ( null !== $app_css ) {
			$this->assertStringContainsString( 'shadowrootmode="open"', $html );
			// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Asserting the inlined shadow stylesheet.
			$this->assertStringContainsString( '<style>', $html );
		}
	}
}

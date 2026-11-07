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
	 * Resets the current user between tests.
	 */
	protected function tearDown(): void {
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * The menu is registered with the expected slug.
	 */
	public function test_menu_is_registered(): void {
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Reset core menu state for the test.
		$GLOBALS['menu'] = array();
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Reset core submenu state for the test.
		$GLOBALS['submenu'] = array();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		( new Menu() )->register();

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook.
		do_action( 'admin_menu' );

		$slugs = array_column( $GLOBALS['menu'], 2 );

		$this->assertContains( Menu::SLUG, $slugs );

		$submenu_slugs = array_column( $GLOBALS['submenu'][ Menu::SLUG ] ?? array(), 2 );

		$this->assertContains( Menu::SLUG, $submenu_slugs );
		$this->assertContains( Menu::SLUG . '-categories', $submenu_slugs );
		$this->assertContains( Menu::SLUG . '-taxonomies', $submenu_slugs );
		$this->assertContains( Menu::SLUG . '-locations', $submenu_slugs );
		$this->assertContains( Menu::SLUG . '-bookings', $submenu_slugs );
		$this->assertContains( Menu::SLUG . '-settings', $submenu_slugs );

		$reevents_events_label = '';

		foreach ( $GLOBALS['submenu'][ Menu::SLUG ] ?? array() as $reevents_submenu_item ) {
			if ( Menu::SLUG === $reevents_submenu_item[2] ) {
				$reevents_events_label = $reevents_submenu_item[0];
				break;
			}
		}

		$this->assertSame( 'Events', $reevents_events_label );

		$reevents_hook = get_plugin_page_hookname( Menu::SLUG . '-categories', Menu::SLUG );

		$this->assertIsString( $reevents_hook );
		$this->assertTrue( Menu::is_screen( 'toplevel_page_' . Menu::SLUG ) );
		$this->assertTrue( Menu::is_screen( $reevents_hook ) );
	}

	/**
	 * Only administrators have the menu capability.
	 */
	public function test_only_administrators_have_menu_capability(): void {
		$reevents_contributor = self::factory()->user->create( array( 'role' => 'contributor' ) );
		$reevents_admin       = self::factory()->user->create( array( 'role' => 'administrator' ) );

		$this->assertFalse( user_can( $reevents_contributor, Menu::CAPABILITY ) );
		$this->assertTrue( user_can( $reevents_admin, Menu::CAPABILITY ) );
	}

	/**
	 * The admin shell renders the React bootstrap data and mount points.
	 */
	public function test_dashboard_uses_base_layout(): void {
		unset( $_GET['page'], $_GET['view'] );

		ob_start();
		( new Menu() )->render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'id="re-events-host"', $html );
		$this->assertStringContainsString( 'id="re-events-template"', $html );
		$this->assertStringContainsString( 'id="re-events-root"', $html );
		$this->assertStringContainsString( 'id="re-events-root" aria-busy="true" hidden', $html );
		$this->assertStringContainsString( 'id="re-events-loading"', $html );
		$this->assertStringContainsString( 'id="re-events-data"', $html );
		$this->assertStringContainsString( '"currentView":"events"', $html );
		$this->assertStringContainsString( 'page=re-events-categories', $html );

		$app_css = ( new Assets() )->inline_css();

		if ( null !== $app_css ) {
			$this->assertStringContainsString( 'shadowrootmode="open"', $html );
			// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Asserting the inlined shadow stylesheet.
			$this->assertStringContainsString( '<style>', $html );
		}
	}

	/**
	 * The current view is passed to the app as bootstrap data.
	 */
	public function test_page_slug_sets_current_view(): void {
		$_GET['page'] = Menu::SLUG . '-locations';

		try {
			ob_start();
			( new Menu() )->render();
			$html = (string) ob_get_clean();
		} finally {
			unset( $_GET['page'] );
		}

		$this->assertStringContainsString( '"currentView":"locations"', $html );
	}

	/**
	 * The legacy view query is ignored.
	 */
	public function test_page_slug_ignores_view_query(): void {
		$_GET['page'] = Menu::SLUG . '-locations';
		$_GET['view'] = 'settings';

		try {
			ob_start();
			( new Menu() )->render();
			$html = (string) ob_get_clean();
		} finally {
			unset( $_GET['page'], $_GET['view'] );
		}

		$this->assertStringContainsString( '"currentView":"locations"', $html );
	}
}

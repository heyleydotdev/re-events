<?php
/**
 * View renderer test.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Tests\Integration;

use ReEvents\Admin\View;
use WP_UnitTestCase;

/**
 * Verifies admin view rendering.
 */
final class View_Test extends WP_UnitTestCase {

	/**
	 * A missing template returns a notice instead of a fatal.
	 */
	public function test_missing_template_returns_notice(): void {
		$reevents_html = View::render( 'pages/does-not-exist', array() );

		$this->assertStringContainsString( 'ReEvents admin view is missing', $reevents_html );
		$this->assertStringContainsString( 'pages/does-not-exist', $reevents_html );
	}
}

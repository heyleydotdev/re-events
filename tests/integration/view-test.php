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
	 * The button component renders the selected variant and size.
	 */
	public function test_button_component_renders_variant_and_size(): void {
		$reevents_html = View::render(
			'components/button',
			array(
				'label'   => 'Save event',
				'variant' => 'success',
				'size'    => 'xs',
			)
		);

		$this->assertStringContainsString( 'bg-success', $reevents_html );
		$this->assertStringContainsString( 'text-xs', $reevents_html );
		$this->assertStringContainsString( 'Save event', $reevents_html );
		$this->assertStringContainsString( 'type="button"', $reevents_html );
	}

	/**
	 * The button component supports icons, icon-only buttons, and loading.
	 */
	public function test_button_component_renders_icons_and_loading(): void {
		$reevents_icon = View::render(
			'components/button',
			array(
				'label'      => '',
				'variant'    => 'outline',
				'size'       => 'xs',
				'icon'       => 'plus',
				'aria_label' => 'Add event',
			)
		);

		$this->assertStringContainsString( 'w-8 h-8', $reevents_icon );
		$this->assertStringContainsString( 'border-brand', $reevents_icon );
		$this->assertStringContainsString( 'aria-label="Add event"', $reevents_icon );
		$this->assertStringContainsString( 'lucide-plus', $reevents_icon );

		$reevents_loading = View::render(
			'components/button',
			array(
				'label'   => 'Saving',
				'loading' => true,
			)
		);

		$this->assertStringContainsString( 'disabled', $reevents_loading );
		$this->assertStringContainsString( 'animate-spin', $reevents_loading );
		$this->assertStringContainsString( 'Saving', $reevents_loading );
	}

	/**
	 * A missing template returns a notice instead of a fatal.
	 */
	public function test_missing_template_returns_notice(): void {
		$reevents_html = View::render( 'pages/does-not-exist', array() );

		$this->assertStringContainsString( 'ReEvents admin view is missing', $reevents_html );
		$this->assertStringContainsString( 'pages/does-not-exist', $reevents_html );
	}
}

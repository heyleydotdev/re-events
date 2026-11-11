<?php
/**
 * Elementor Venues List widget.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Builders\Elementor;

/**
 * Renders the Venues List widget.
 */
final class Venues_List extends Widget {

	/**
	 * Registry widget ID.
	 */
	protected function widget_id(): string {
		return 'venues-list';
	}
}

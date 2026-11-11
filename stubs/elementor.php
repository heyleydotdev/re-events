<?php
/**
 * Minimal Elementor stubs for static analysis.
 *
 * @package ReEvents
 */

namespace Elementor;

/**
 * Elementor controls manager.
 */
class Controls_Manager {

	public const TEXT      = 'text';
	public const TEXTAREA  = 'textarea';
	public const NUMBER    = 'number';
	public const SWITCHER  = 'switcher';
	public const COLOR     = 'color';
	public const DATE_TIME = 'date_time';
}

/**
 * Elementor widget base.
 */
class Widget_Base {

	/**
	 * Build a widget.
	 *
	 * @param array<string, mixed> $data Widget data.
	 * @param mixed                $args Widget arguments.
	 */
	public function __construct( array $data = [], $args = null ) {}

	public function get_name(): string {
		return '';
	}

	public function get_title(): string {
		return '';
	}

	public function get_icon(): string {
		return '';
	}

	/**
	 * Widget categories.
	 *
	 * @return array<int, string>
	 */
	public function get_categories(): array {
		return [];
	}

	/**
	 * Settings for display.
	 *
	 * @param string|null $setting_key Setting key.
	 * @return array<string, mixed>
	 */
	public function get_settings_for_display( $setting_key = null ): array {
		return [];
	}

	/**
	 * Register a control.
	 *
	 * @param string               $id      Control ID.
	 * @param array<string, mixed> $args    Control arguments.
	 * @param array<string, mixed> $options Control options.
	 */
	public function add_control( string $id, array $args = [], array $options = [] ): void {}

	/**
	 * Start a controls section.
	 *
	 * @param string               $id   Section ID.
	 * @param array<string, mixed> $args Section arguments.
	 */
	public function start_controls_section( string $id, array $args = [] ): void {}

	public function end_controls_section(): void {}

	protected function register_controls(): void {}

	protected function render(): void {}
}

/**
 * Elementor elements manager.
 */
class Elements_Manager {

	/**
	 * Add a category.
	 *
	 * @param string               $category_name Category name.
	 * @param array<string, mixed> $args          Category arguments.
	 */
	public function add_category( string $category_name, array $args = [] ): void {}
}

/**
 * Elementor widgets manager.
 */
class Widgets_Manager {

	/**
	 * Register a widget.
	 *
	 * @param Widget_Base $widget Widget.
	 */
	public function register( Widget_Base $widget ): void {}
}

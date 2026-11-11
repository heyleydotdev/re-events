<?php
/**
 * Shared Elementor widget.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Builders\Elementor;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use ReEvents\Builders\Controls;
use ReEvents\Render\Widget_Registry;

/**
 * Renders one registry widget from native Elementor controls.
 */
abstract class Widget extends Widget_Base {

	/**
	 * Registry widget ID.
	 */
	abstract protected function widget_id(): string;

	/**
	 * Elementor widget name.
	 */
	public function get_name(): string {
		return 'reevents-' . $this->widget_id();
	}

	/**
	 * Elementor widget title.
	 */
	public function get_title(): string {
		$widget = Widget_Registry::get( $this->widget_id() );

		return (string) ( $widget['label'] ?? $this->widget_id() );
	}

	/**
	 * Elementor icon.
	 */
	public function get_icon(): string {
		return 'eicon-calendar';
	}

	/**
	 * Elementor category.
	 *
	 * @return array<int, string>
	 */
	public function get_categories(): array {
		return [ 'reevents' ];
	}

	/**
	 * Register the controls from the widget schema.
	 */
	protected function register_controls(): void {
		$widget = Widget_Registry::get( $this->widget_id() );

		if ( ! $widget ) {
			return;
		}

		$this->start_controls_section(
			'reevents_content',
			[ 'label' => __( 'Content', 're-events' ) ]
		);

		foreach ( Controls::for_widget( $widget ) as $key => $control ) {
			$this->add_control( $key, $this->control_args( $control ) );
		}

		$this->end_controls_section();
	}

	/**
	 * Render the widget.
	 */
	protected function render(): void {
		$args     = [];
		$settings = $this->get_settings_for_display();

		foreach ( (array) ( Widget_Registry::get( $this->widget_id() )['args'] ?? [] ) as $key => $_definition ) {
			if ( array_key_exists( $key, $settings ) ) {
				$args[ $key ] = $settings[ $key ];
			}
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escapes its template output.
		echo Widget_Registry::render( $this->widget_id(), $args );
	}

	/**
	 * Map a control definition to Elementor arguments.
	 *
	 * @param array{type:string,label:string,description:string,default:mixed} $control Control.
	 * @return array<string, mixed>
	 */
	private function control_args( array $control ): array {
		$args = [
			'label'   => $control['label'],
			'default' => $control['default'],
		];

		if ( '' !== $control['description'] ) {
			$args['description'] = $control['description'];
		}

		switch ( $control['type'] ) {
			case 'number':
				$args['type'] = Controls_Manager::NUMBER;
				$args['min']  = 0;
				break;

			case 'toggle':
				$args['type']         = Controls_Manager::SWITCHER;
				$args['return_value'] = 'yes';
				$args['default']      = ! empty( $control['default'] ) ? 'yes' : '';
				break;

			case 'textarea':
				$args['type'] = Controls_Manager::TEXTAREA;
				break;

			case 'color':
				$args['type'] = Controls_Manager::COLOR;
				break;

			case 'date':
				$args['type'] = Controls_Manager::DATE_TIME;
				break;

			default:
				$args['type'] = Controls_Manager::TEXT;
				break;
		}

		return $args;
	}
}

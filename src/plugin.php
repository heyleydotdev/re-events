<?php
/**
 * Plugin bootstrap.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents;

use ReEvents\Admin\Menu;
use ReEvents\Data\Meta;
use ReEvents\Data\Post_Types;
use ReEvents\Data\Taxonomies;
/**
 * Wires the plugin services together.
 */
final class Plugin {

	/**
	 * Shared instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Get the shared instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register hooks.
	 */
	public function boot(): void {
		add_action( 'init', [ $this, 'load_textdomain' ] );
		add_action( 'admin_init', [ Activation::class, 'maybe_upgrade' ] );
		Post_Types::register();
		Taxonomies::register();
		Meta::register();
		Menu::register();
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 're-events', false, dirname( plugin_basename( RE_EVENTS_FILE ) ) . '/languages' );
	}
}

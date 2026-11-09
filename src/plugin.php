<?php
/**
 * Plugin bootstrap.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents;

use ReEvents\Admin\API_Docs;
use ReEvents\Admin\Assets;
use ReEvents\Admin\Event_List;
use ReEvents\Admin\Menu;
use ReEvents\Admin\Meta_Boxes;
use ReEvents\Admin\Settings;
use ReEvents\Admin\Venue_Meta_Boxes;
use ReEvents\CLI\OpenAPI;
use ReEvents\CLI\Self_Test;
use ReEvents\Frontend\Assets as Frontend_Assets;
use ReEvents\Frontend\Blocks;
use ReEvents\Frontend\Shortcodes;
use ReEvents\Render\Widget_Registry;
use ReEvents\REST\Routes;
use ReEvents\Services\Occurrence_Service;
use ReEvents\Data\Meta;
use ReEvents\Data\Post_Types;
use ReEvents\Data\Schema;
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
		add_action( 'init', [ Schema::class, 'maybe_install' ] );
		add_action( 'admin_init', [ Activation::class, 'maybe_upgrade' ] );
		Post_Types::register();
		Taxonomies::register();
		Meta::register();
		Meta_Boxes::register();
		Venue_Meta_Boxes::register();
		Assets::register();
		API_Docs::register();
		Settings::register();
		Event_List::register();
		Widget_Registry::register_defaults();
		Shortcodes::register();
		Blocks::register();
		Routes::register();
		Occurrence_Service::register();
		Frontend_Assets::register();
		Menu::register();

		add_action(
			'cli_init',
			static function (): void {
				if ( class_exists( OpenAPI::class ) ) {
					\WP_CLI::add_command( 're-events openapi', [ OpenAPI::class, 'generate' ] );
				}

				if ( class_exists( Self_Test::class ) ) {
					\WP_CLI::add_command( 're-events self-test', [ Self_Test::class, 'run' ] );
				}
			}
		);
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 're-events', false, dirname( plugin_basename( RE_EVENTS_FILE ) ) . '/languages' );
	}
}

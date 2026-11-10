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
use ReEvents\Admin\Attendees;
use ReEvents\Admin\Bookings;
use ReEvents\Admin\Event_Filter;
use ReEvents\Admin\Event_List;
use ReEvents\Admin\Export;
use ReEvents\Admin\Help;
use ReEvents\Admin\Menu;
use ReEvents\Admin\Meta_Boxes;
use ReEvents\Admin\Notices;
use ReEvents\Admin\Quick_View;
use ReEvents\Admin\Settings;
use ReEvents\Admin\Venue_Meta_Boxes;
use ReEvents\CLI\Concurrency_Test;
use ReEvents\CLI\OpenAPI;
use ReEvents\CLI\Bench;
use ReEvents\CLI\Export_Attendees;
use ReEvents\CLI\Import_Export;
use ReEvents\CLI\Self_Test;
use ReEvents\CLI\Shortcode_Docs;
use ReEvents\Frontend\Assets as Frontend_Assets;
use ReEvents\Frontend\Blocks;
use ReEvents\Frontend\Booking_Handler;
use ReEvents\Frontend\Feeds;
use ReEvents\Frontend\Payment_Return;
use ReEvents\Frontend\Shortcodes;
use ReEvents\Frontend\Structured_Data;
use ReEvents\Render\Widget_Registry;
use ReEvents\REST\Routes;
use ReEvents\Services\API_Keys;
use ReEvents\Services\Booking_Emails;
use ReEvents\Services\Booking_Service;
use ReEvents\Services\Geocoder;
use ReEvents\Services\Occurrence_Service;
use ReEvents\Services\Payment_Service;
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
		add_action( 'init', [ $this, 'load_textdomain' ], 1 );
		add_action( 'init', [ Schema::class, 'maybe_install' ] );
		add_action( 'admin_init', [ Activation::class, 'maybe_upgrade' ] );
		Post_Types::register();
		Taxonomies::register();
		Meta::register();
		Meta_Boxes::register();
		Venue_Meta_Boxes::register();
		Assets::register();
		API_Docs::register();
		Attendees::register();
		Bookings::register();
		Export::register();
		Help::register();
		Notices::register();
		Quick_View::register();
		Event_Filter::register();
		Settings::register();
		Event_List::register();
		add_action( 'init', [ Widget_Registry::class, 'register_defaults' ], 2 );
		add_action( 'init', [ Shortcodes::class, 'register' ], 3 );
		Blocks::register();
		Routes::register();
		Occurrence_Service::register();
		Geocoder::register();
		Booking_Service::register();
		Booking_Emails::register();
		Payment_Service::register();
		API_Keys::register();
		Booking_Handler::register();
		Payment_Return::register();
		Feeds::register();
		Structured_Data::register();
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

				if ( class_exists( Export_Attendees::class ) ) {
					\WP_CLI::add_command( 're-events export-attendees', [ Export_Attendees::class, 'run' ] );
				}

				if ( class_exists( Import_Export::class ) ) {
					\WP_CLI::add_command( 're-events import', [ Import_Export::class, 'import' ] );
					\WP_CLI::add_command( 're-events export', [ Import_Export::class, 'export' ] );
					\WP_CLI::add_command( 're-events expand-occurrences', [ Import_Export::class, 'expand_occurrences' ] );
				}

				if ( class_exists( Bench::class ) ) {
					\WP_CLI::add_command( 're-events bench', Bench::class );
				}

				if ( class_exists( Concurrency_Test::class ) ) {
					\WP_CLI::add_command( 're-events concurrency-test', [ Concurrency_Test::class, 'run' ] );
				}

				if ( class_exists( Shortcode_Docs::class ) ) {
					\WP_CLI::add_command( 're-events shortcode-docs', [ Shortcode_Docs::class, 'run' ] );
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

<?php
/**
 * Admin menu.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Admin;

/**
 * Registers the ReEvents admin menu and submenus.
 */
final class Menu {

	/**
	 * Menu slug.
	 */
	public const SLUG = 're-events';

	/**
	 * Capability required to view the menu.
	 */
	public const CAPABILITY = 'manage_options';

	/**
	 * Registered screen hook suffixes.
	 *
	 * @var array<int, string>
	 */
	private static array $screens = array();

	/**
	 * Registers the admin menu hook.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
	}

	/**
	 * Adds the top-level menu and its section submenus.
	 */
	public function add_menu(): void {
		$reevents_hook = add_menu_page(
			__( 'ReEvents', 're-events' ),
			__( 'ReEvents', 're-events' ),
			self::CAPABILITY,
			self::SLUG,
			array( $this, 'render' ),
			'dashicons-calendar-alt',
			26
		);

		if ( is_string( $reevents_hook ) ) {
			self::$screens[] = $reevents_hook;
		}

		foreach ( $this->views() as $view => $label ) {
			if ( 'events' === $view ) {
				continue;
			}

			$reevents_submenu_hook = add_submenu_page(
				self::SLUG,
				$label,
				$label,
				self::CAPABILITY,
				$this->page_slug( $view ),
				array( $this, 'render' )
			);

			if ( is_string( $reevents_submenu_hook ) ) {
				self::$screens[] = $reevents_submenu_hook;
			}
		}

		// WordPress adds the parent page as the first submenu; label it Events.
		global $submenu;

		if ( isset( $submenu[ self::SLUG ] ) ) {
			foreach ( $submenu[ self::SLUG ] as $index => $item ) {
				if ( self::SLUG === $item[2] ) {
					// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Rename the auto parent submenu item.
					$submenu[ self::SLUG ][ $index ][0] = __( 'Events', 're-events' );
					break;
				}
			}
		}
	}

	/**
	 * Whether the current screen belongs to ReEvents.
	 *
	 * @param string $hook_suffix Current admin screen.
	 */
	public static function is_screen( string $hook_suffix ): bool {
		return in_array( $hook_suffix, self::$screens, true );
	}

	/**
	 * Renders the selected ReEvents view.
	 */
	public function render(): void {
		$reevents_home_url  = admin_url( 'admin.php?page=' . $this->page_slug( 'events' ) );
		$reevents_assets    = new Assets();
		$reevents_nav_items = $this->nav_items();
		$reevents_view      = $this->current_view( $reevents_nav_items );

		$reevents_view_label  = $reevents_nav_items[ $reevents_view ]['label'];
		$reevents_app_css     = $reevents_assets->inline_css();
		$reevents_declarative = ! $reevents_assets->is_dev() && null !== $reevents_app_css;
		$reevents_html        = View::render(
			'pages/placeholder',
			array(
				'view_label'   => $reevents_view_label,
				'app_css'      => (string) $reevents_app_css,
				'declarative'  => $reevents_declarative,
				'nav_items'    => $reevents_nav_items,
				'current_view' => $reevents_view,
				'home_url'     => $reevents_home_url,
			)
		);

		echo $reevents_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plates-rendered template.
	}

	/**
	 * Returns the section labels keyed by view.
	 *
	 * @return array<string, string>
	 */
	private function views(): array {
		return array(
			'events'     => __( 'Events', 're-events' ),
			'categories' => __( 'Categories', 're-events' ),
			'taxonomies' => __( 'Taxonomies', 're-events' ),
			'locations'  => __( 'Locations', 're-events' ),
			'bookings'   => __( 'Bookings', 're-events' ),
			'settings'   => __( 'Settings', 're-events' ),
		);
	}

	/**
	 * Returns the admin page slug for a view.
	 *
	 * @param string $view View key.
	 */
	private function page_slug( string $view ): string {
		return 'events' === $view ? self::SLUG : self::SLUG . '-' . $view;
	}

	/**
	 * Returns the nav items.
	 *
	 * @return array<string, array{label: string, url: string}>
	 */
	private function nav_items(): array {
		$items = array();

		foreach ( $this->views() as $view => $label ) {
			$items[ $view ] = array(
				'label' => $label,
				'url'   => admin_url( 'admin.php?page=' . $this->page_slug( $view ) ),
			);
		}

		return $items;
	}

	/**
	 * Resolves the current view from the page slug.
	 *
	 * @param array<string, array{label: string, url: string}> $nav_items Nav items.
	 */
	private function current_view( array $nav_items ): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only page selector.
		$reevents_page = '';

		if ( isset( $_GET['page'] ) && is_string( $_GET['page'] ) ) {
			$reevents_page = sanitize_key( wp_unslash( $_GET['page'] ) );
		}
		// phpcs:enable

		if ( self::SLUG === $reevents_page ) {
			return 'events';
		}

		foreach ( array_keys( $nav_items ) as $view ) {
			if ( $this->page_slug( $view ) === $reevents_page ) {
				return $view;
			}
		}

		return 'events';
	}
}

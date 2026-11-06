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
	 * Renders the admin shell and bootstrap data for the React app.
	 */
	public function render(): void {
		$reevents_assets    = new Assets();
		$reevents_nav_items = $this->nav_items();
		$reevents_view      = $this->current_view( $reevents_nav_items );
		$reevents_nav       = array();

		foreach ( $reevents_nav_items as $reevents_key => $reevents_item ) {
			$reevents_nav[] = array(
				'key'   => $reevents_key,
				'label' => $reevents_item['label'],
				'url'   => $reevents_item['url'],
			);
		}

		$reevents_events = array(
			array(
				'id'     => 1,
				'title'  => 'WordPress Meetup',
				'date'   => 'Nov 12, 2026',
				'venue'  => 'Berlin',
				'status' => __( 'Published', 're-events' ),
				'tone'   => 'success',
			),
			array(
				'id'     => 2,
				'title'  => 'Design Systems Workshop',
				'date'   => 'Nov 20, 2026',
				'venue'  => 'Online',
				'status' => __( 'Draft', 're-events' ),
				'tone'   => 'warning',
			),
			array(
				'id'     => 3,
				'title'  => 'ReEvents Launch Party',
				'date'   => 'Dec 3, 2026',
				'venue'  => 'Amsterdam',
				'status' => __( 'Pending', 're-events' ),
				'tone'   => 'neutral',
			),
		);

		$reevents_data = array(
			'currentView' => $reevents_view,
			'homeUrl'     => admin_url( 'admin.php?page=' . self::SLUG ),
			'nav'         => $reevents_nav,
			'events'      => $reevents_events,
			'pagination'  => array(
				'current' => 1,
				'total'   => 1,
				'summary' => __( 'Showing 3 of 3 events', 're-events' ),
			),
			'restUrl'     => rest_url( 're-events/v1/events' ),
			'nonce'       => wp_create_nonce( 'wp_rest' ),
			'canManage'   => current_user_can( self::CAPABILITY ),
		);

		$reevents_app_css = $reevents_assets->is_dev() ? null : $reevents_assets->inline_css();
		?>
		<div id="re-events-host">
			<template id="re-events-template" shadowrootmode="open">
				<?php if ( null !== $reevents_app_css && '' !== $reevents_app_css ) : ?>
					<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Shadow DOM stylesheet. ?>
					<style><?php echo $reevents_app_css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built CSS asset. ?></style>
				<?php endif; ?>
				<div id="re-events-loading" role="status" aria-live="polite">
					<svg width="20" height="20" viewBox="0 0 50 50" aria-hidden="true" focusable="false">
						<circle cx="25" cy="25" r="20" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round" stroke-dasharray="31.4 31.4"></circle>
					</svg>
					<span><?php esc_html_e( 'Loading…', 're-events' ); ?></span>
				</div>
				<div id="re-events-root" class="min-h-[calc(100svh-var(--wp-admin--admin-bar--height))] opacity-0" aria-busy="true"></div>
			</template>
			<script type="application/json" id="re-events-data">
				<?php echo wp_json_encode( $reevents_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON data. ?>
			</script>
			<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- Fallback for browsers without declarative shadow DOM. ?>
			<script>( function () {
				var host = document.getElementById( 're-events-host' );
				var template = host && host.querySelector( '#re-events-template' );

				if ( host && template && ! host.shadowRoot ) {
					host.attachShadow( { mode: 'open' } ).appendChild( template.content.cloneNode( true ) );
					template.remove();
				}
			}() );</script>
		</div>
		<?php
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

<?php
/**
 * Admin menu.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Admin;

/**
 * Registers the top-level ReEvents menu.
 */
final class Menu {

	/**
	 * Menu slug.
	 */
	public const SLUG = 're-events';

	/**
	 * Capability required to view the menu.
	 */
	public const CAPABILITY = 'edit_posts';

	/**
	 * Registers the admin menu hook.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
	}

	/**
	 * Adds the top-level menu.
	 */
	public function add_menu(): void {
		add_menu_page(
			__( 'ReEvents', 're-events' ),
			__( 'ReEvents', 're-events' ),
			self::CAPABILITY,
			self::SLUG,
			array( $this, 'render' ),
			'dashicons-calendar-alt',
			25
		);
	}

	/**
	 * Renders the selected ReEvents view.
	 */
	public function render(): void {
		$reevents_home_url = admin_url( 'admin.php?page=' . self::SLUG );
		$reevents_assets   = new Assets();

		$reevents_nav_items = array(
			'events'     => array(
				'label' => __( 'Events', 're-events' ),
				'url'   => add_query_arg( 'view', 'events', $reevents_home_url ),
			),
			'categories' => array(
				'label' => __( 'Categories', 're-events' ),
				'url'   => add_query_arg( 'view', 'categories', $reevents_home_url ),
			),
			'taxonomies' => array(
				'label' => __( 'Taxonomies', 're-events' ),
				'url'   => add_query_arg( 'view', 'taxonomies', $reevents_home_url ),
			),
			'locations'  => array(
				'label' => __( 'Locations', 're-events' ),
				'url'   => add_query_arg( 'view', 'locations', $reevents_home_url ),
			),
			'bookings'   => array(
				'label' => __( 'Bookings', 're-events' ),
				'url'   => add_query_arg( 'view', 'bookings', $reevents_home_url ),
			),
		);

		$reevents_current_view = 'events';

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only view selector.
		if ( isset( $_GET['view'] ) && is_string( $_GET['view'] ) ) {
			$reevents_requested_view = sanitize_key( wp_unslash( $_GET['view'] ) );

			if ( isset( $reevents_nav_items[ $reevents_requested_view ] ) ) {
				$reevents_current_view = $reevents_requested_view;
			}
		}
		// phpcs:enable

		$reevents_view_label  = $reevents_nav_items[ $reevents_current_view ]['label'];
		$reevents_app_css     = $reevents_assets->inline_css();
		$reevents_declarative = ! $reevents_assets->is_dev() && null !== $reevents_app_css;
		$reevents_html        = View::render(
			'pages/placeholder',
			array(
				'view_label'   => $reevents_view_label,
				'app_css'      => (string) $reevents_app_css,
				'declarative'  => $reevents_declarative,
				'nav_items'    => $reevents_nav_items,
				'current_view' => $reevents_current_view,
				'home_url'     => $reevents_home_url,
			)
		);

		echo $reevents_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plates-rendered template.
	}
}

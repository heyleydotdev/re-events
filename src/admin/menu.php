<?php
/**
 * Admin menu.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Admin;

use WP_Screen;

/**
 * Registers the ReEvents admin menu.
 */
final class Menu {

	/**
	 * Hook into WordPress.
	 */
	public static function register(): void {
		add_action( 'admin_menu', [ self::class, 'add' ] );
		add_filter( 'parent_file', [ self::class, 'fix_taxonomy_parent' ] );
		add_filter( 'submenu_file', [ self::class, 'fix_taxonomy_submenu' ] );
		add_action( 'admin_menu', [ self::class, 'sort_submenus' ], 99 );
	}

	/**
	 * Add the top-level menu.
	 */
	public static function add(): void {
		if ( ! current_user_can( 'edit_reevents_events' ) && ! current_user_can( 'edit_reevents_venues' ) && ! current_user_can( 'edit_reevents_organizers' ) && ! current_user_can( 'reevents_manage_bookings' ) && ! current_user_can( 'reevents_manage_settings' ) ) {
			return;
		}

		add_menu_page(
			__( 'ReEvents', 're-events' ),
			__( 'ReEvents', 're-events' ),
			'read',
			're-events',
			[ self::class, 'render' ],
			'dashicons-calendar-alt',
			30
		);

		self::add_taxonomy_submenus();
	}

	/**
	 * Add taxonomy screens under the ReEvents menu.
	 *
	 * Core only adds taxonomy submenus to top-level post type menus, so
	 * post types nested under a custom menu need this.
	 */
	private static function add_taxonomy_submenus(): void {
		$position = 3;

		foreach ( [ 'reevents_event_category', 'reevents_event_tag', 'reevents_organizer_type' ] as $taxonomy_name ) {
			$taxonomy = get_taxonomy( $taxonomy_name );

			if ( ! $taxonomy ) {
				continue;
			}

			$post_type = (string) ( $taxonomy->object_type[0] ?? '' );

			add_submenu_page(
				're-events',
				$taxonomy->labels->name,
				$taxonomy->labels->menu_name,
				$taxonomy->cap->manage_terms,
				'edit-tags.php?taxonomy=' . $taxonomy->name . '&post_type=' . $post_type,
				'',
				$position++
			);
		}
	}

	/**
	 * Sort ReEvents submenus in a predictable order.
	 */
	public static function sort_submenus(): void {
		global $submenu;

		if ( empty( $submenu['re-events'] ) ) {
			return;
		}

		$order = [
			'edit.php?post_type=reevents_event'     => 0,
			'edit.php?post_type=reevents_venue'     => 1,
			'edit.php?post_type=reevents_organizer' => 2,
			'edit-tags.php?taxonomy=reevents_event_category&post_type=reevents_event' => 3,
			'edit-tags.php?taxonomy=reevents_event_tag&post_type=reevents_event' => 4,
			'edit-tags.php?taxonomy=reevents_organizer_type&post_type=reevents_organizer' => 5,
			'reevents-bookings'                     => 6,
			'reevents-attendees'                    => 7,
			'reevents-settings'                     => 8,
			'reevents-api-docs'                     => 9,
			'reevents-help'                         => 10,
		];

		usort(
			$submenu['re-events'],
			static function ( array $a, array $b ) use ( $order ): int {
				return ( $order[ $a[2] ] ?? 99 ) <=> ( $order[ $b[2] ] ?? 99 );
			}
		);
	}

	/**
	 * Keep the ReEvents menu highlighted on taxonomy screens.
	 *
	 * @param string|null $parent_file Current parent file.
	 */
	public static function fix_taxonomy_parent( ?string $parent_file ): ?string {
		return self::plugin_taxonomy_screen() ? 're-events' : $parent_file;
	}

	/**
	 * Highlight the current taxonomy submenu item.
	 *
	 * @param string|null $submenu_file Current submenu file.
	 */
	public static function fix_taxonomy_submenu( ?string $submenu_file ): ?string {
		$screen = self::plugin_taxonomy_screen();

		if ( ! $screen ) {
			return $submenu_file;
		}

		return 'edit-tags.php?taxonomy=' . $screen->taxonomy . '&post_type=' . $screen->post_type;
	}

	/**
	 * Get the current screen when it is a plugin taxonomy screen.
	 */
	private static function plugin_taxonomy_screen(): ?WP_Screen {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return null;
		}

		if ( ! in_array( $screen->taxonomy, [ 'reevents_event_category', 'reevents_event_tag', 'reevents_organizer_type' ], true ) ) {
			return null;
		}

		return $screen;
	}

	/**
	 * Render the admin dashboard.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'edit_reevents_events' ) && ! current_user_can( 'edit_reevents_venues' ) && ! current_user_can( 'edit_reevents_organizers' ) && ! current_user_can( 'reevents_manage_bookings' ) && ! current_user_can( 'reevents_manage_settings' ) ) {
			return;
		}

		$events     = wp_count_posts( 'reevents_event' );
		$venues     = wp_count_posts( 'reevents_venue' );
		$organizers = wp_count_posts( 'reevents_organizer' );

		$cards = [];

		if ( current_user_can( 'edit_reevents_events' ) ) {
			$cards[] = [
				'label' => __( 'Events', 're-events' ),
				'count' => (int) ( $events->publish ?? 0 ),
				'url'   => admin_url( 'edit.php?post_type=reevents_event' ),
			];
		}

		if ( current_user_can( 'edit_reevents_venues' ) ) {
			$cards[] = [
				'label' => __( 'Venues', 're-events' ),
				'count' => (int) ( $venues->publish ?? 0 ),
				'url'   => admin_url( 'edit.php?post_type=reevents_venue' ),
			];
		}

		if ( current_user_can( 'edit_reevents_organizers' ) ) {
			$cards[] = [
				'label' => __( 'Organizers', 're-events' ),
				'count' => (int) ( $organizers->publish ?? 0 ),
				'url'   => admin_url( 'edit.php?post_type=reevents_organizer' ),
			];
		}

		if ( current_user_can( 'reevents_manage_bookings' ) ) {
			$cards[] = [
				'label' => __( 'Bookings', 're-events' ),
				'count' => null,
				'url'   => admin_url( 'admin.php?page=reevents-bookings' ),
			];
			$cards[] = [
				'label' => __( 'Attendees', 're-events' ),
				'count' => null,
				'url'   => admin_url( 'admin.php?page=reevents-attendees' ),
			];
		}

		if ( current_user_can( 'reevents_manage_settings' ) ) {
			$cards[] = [
				'label' => __( 'Settings', 're-events' ),
				'count' => null,
				'url'   => admin_url( 'admin.php?page=reevents-settings' ),
			];
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'ReEvents', 're-events' ); ?></h1>
			<div class="reevents-dashboard" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:1em;margin-top:1em;">
				<?php foreach ( $cards as $card ) : ?>
					<a class="card" href="<?php echo esc_url( $card['url'] ); ?>" style="display:block;padding:1em;text-decoration:none;">
						<strong style="display:block;font-size:1.4em;"><?php echo esc_html( $card['label'] ); ?></strong>
						<?php if ( null !== $card['count'] ) : ?>
							<span><?php echo esc_html( number_format_i18n( $card['count'] ) ); ?></span>
						<?php endif; ?>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}
}

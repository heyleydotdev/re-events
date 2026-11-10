<?php
/**
 * Admin CSV export buttons.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Admin;

use ReEvents\Services\Csv;

/**
 * Adds CSV export buttons and streams downloads.
 */
final class Export {

	/**
	 * Hook into WordPress.
	 */
	public static function register(): void {
		add_action( 'admin_post_reevents_export_csv', [ self::class, 'handle' ] );
		add_action( 'restrict_manage_posts', [ self::class, 'post_type_button' ] );
		add_action( 'admin_footer-edit.php', [ self::class, 'relocate_button' ] );
	}

	/**
	 * Export capabilities per type.
	 *
	 * @return array<string, string>
	 */
	private static function capabilities(): array {
		return [
			'events'    => 'edit_reevents_events',
			'venues'    => 'edit_reevents_venues',
			'bookings'  => 'reevents_manage_bookings',
			'attendees' => 'reevents_manage_bookings',
		];
	}

	/**
	 * Add the hidden button to the event and venue list tables.
	 *
	 * The footer script moves it next to the page title action.
	 *
	 * @param string $post_type Current post type.
	 */
	public static function post_type_button( string $post_type ): void {
		if ( 'reevents_event' === $post_type ) {
			self::button( 'events' );
		} elseif ( 'reevents_venue' === $post_type ) {
			self::button( 'venues' );
		}
	}

	/**
	 * Output a hidden export button for a list table.
	 *
	 * @param string $type Export type.
	 */
	public static function button( string $type ): void {
		if ( ! self::allowed( $type ) ) {
			return;
		}

		echo '<a class="button reevents-export-button" style="display:none" href="' . esc_url( self::url( $type ) ) . '">' . esc_html__( 'Export CSV', 're-events' ) . '</a>';
	}

	/**
	 * Output an export button beside a page title.
	 *
	 * @param string $type Export type.
	 */
	public static function title_button( string $type ): void {
		if ( ! self::allowed( $type ) ) {
			return;
		}

		echo '<a class="page-title-action" style="margin-left:6px" href="' . esc_url( self::url( $type ) ) . '">' . esc_html__( 'Export CSV', 're-events' ) . '</a>';
	}

	/**
	 * Whether the current user can export this type.
	 *
	 * @param string $type Export type.
	 */
	private static function allowed( string $type ): bool {
		$caps = self::capabilities();

		return isset( $caps[ $type ] ) && current_user_can( $caps[ $type ] );
	}

	/**
	 * Build the export URL.
	 *
	 * @param string $type Export type.
	 */
	private static function url( string $type ): string {
		if ( 'attendees' === $type ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter.
			$event_id = isset( $_GET['event'] ) ? absint( wp_unslash( $_GET['event'] ) ) : 0;

			return wp_nonce_url(
				add_query_arg(
					[
						'action' => 'reevents_export_attendees',
						'event'  => $event_id,
					],
					admin_url( 'admin-post.php' )
				),
				'reevents_export_attendees'
			);
		}

		return wp_nonce_url(
			add_query_arg(
				[
					'action' => 'reevents_export_csv',
					'type'   => $type,
				],
				admin_url( 'admin-post.php' )
			),
			'reevents_export_csv'
		);
	}

	/**
	 * Move the hidden button next to the page title action.
	 */
	public static function relocate_button(): void {
		$screen = get_current_screen();

		if ( ! $screen || ! in_array( $screen->post_type, [ 'reevents_event', 'reevents_venue' ], true ) ) {
			return;
		}
		?>
		<script>
			document.querySelectorAll( '.reevents-export-button' ).forEach( function ( button ) {
				const heading = document.querySelector( '.wrap h1.wp-heading-inline' ) || document.querySelector( '.wrap h1' );

				if ( ! heading ) {
					return;
				}

				button.classList.remove( 'button' );
				button.classList.add( 'page-title-action' );
				button.style.marginLeft = '6px';

				const addNew = heading.parentNode.querySelector( 'a.page-title-action' );

				if ( addNew ) {
					addNew.insertAdjacentElement( 'afterend', button );
				} else {
					heading.insertAdjacentElement( 'afterend', button );
				}

				button.style.display = '';
			} );
		</script>
		<?php
	}

	/**
	 * Stream a CSV download.
	 */
	public static function handle(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce checked below.
		$type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( (string) $_GET['type'] ) ) : '';
		$caps = self::capabilities();

		if ( ! isset( $caps[ $type ] ) || ! current_user_can( $caps[ $type ] ) ) {
			wp_die( esc_html__( 'Forbidden', 're-events' ), 403 );
		}

		check_admin_referer( 'reevents_export_csv' );

		$header = Csv::header( $type );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $type . '.csv' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- CSV download.
		$output = fopen( 'php://output', 'w' );

		if ( false !== $output && $header ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv -- CSV download.
			fputcsv( $output, $header );

			foreach ( Csv::rows( $type ) as $row ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv -- CSV download.
				fputcsv( $output, $row );
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- CSV download.
			fclose( $output );
		}

		exit;
	}
}

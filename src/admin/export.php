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
			'events'   => 'edit_reevents_events',
			'venues'   => 'edit_reevents_venues',
			'bookings' => 'reevents_manage_bookings',
		];
	}

	/**
	 * Add the button to the event and venue list tables.
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
	 * Output an export button.
	 *
	 * @param string $type Export type.
	 */
	public static function button( string $type ): void {
		if ( ! isset( self::capabilities()[ $type ] ) || ! current_user_can( self::capabilities()[ $type ] ) ) {
			return;
		}

		$url = wp_nonce_url(
			add_query_arg(
				[
					'action' => 'reevents_export_csv',
					'type'   => $type,
				],
				admin_url( 'admin-post.php' )
			),
			'reevents_export_csv'
		);

		echo '<a class="button reevents-export-button" href="' . esc_url( $url ) . '">' . esc_html__( 'Export CSV', 're-events' ) . '</a>';
	}

	/**
	 * Move the export button next to the page title action.
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

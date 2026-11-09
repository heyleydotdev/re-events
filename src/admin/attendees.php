<?php
/**
 * Attendees admin page.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Admin;

use ReEvents\Services\Attendee_Export;
use ReEvents\Services\Booking_Emails;

/**
 * Lists attendees and exports them as CSV.
 */
final class Attendees {

	/**
	 * Hook into WordPress.
	 */
	public static function register(): void {
		add_action( 'admin_menu', [ self::class, 'add_page' ], 13 );
		add_action( 'admin_post_reevents_export_attendees', [ self::class, 'export' ] );
		add_action( 'admin_post_reevents_resend_booking_email', [ self::class, 'resend' ] );
	}

	/**
	 * Add the attendees page.
	 */
	public static function add_page(): void {
		$hook = add_submenu_page(
			're-events',
			__( 'Attendees', 're-events' ),
			__( 'Attendees', 're-events' ),
			'reevents_manage_bookings',
			'reevents-attendees',
			[ self::class, 'render' ],
			6
		);

		if ( $hook ) {
			add_filter( 'manage_' . $hook . '_columns', [ Attendees_Table::class, 'default_columns' ], 0 );
		}
	}

	/**
	 * Render the attendee list.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'reevents_manage_bookings' ) ) {
			return;
		}

		$table = new Attendees_Table();
		$table->prepare_items();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Attendees', 're-events' ); ?></h1>
			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice.
			$notice = isset( $_GET['reevents_notice'] ) ? sanitize_key( wp_unslash( $_GET['reevents_notice'] ) ) : '';

			if ( 'resent' === $notice ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Email resent.', 're-events' ) . '</p></div>';
			}
			?>
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
				<input type="hidden" name="page" value="reevents-attendees" />
				<?php
				$table->search_box( __( 'Search attendees', 're-events' ), 'reevents-attendees' );
				$table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Stream the attendee CSV.
	 */
	public static function export(): void {
		if ( ! current_user_can( 'reevents_manage_bookings' ) ) {
			wp_die( esc_html__( 'Forbidden', 're-events' ), 403 );
		}

		check_admin_referer( 'reevents_export_attendees' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce checked above.
		$event_id = isset( $_GET['event'] ) ? absint( wp_unslash( $_GET['event'] ) ) : 0;

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=attendees.csv' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- CSV download.
		$output = fopen( 'php://output', 'w' );

		if ( false !== $output ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv -- CSV download.
			fputcsv( $output, Attendee_Export::header() );

			foreach ( Attendee_Export::rows( $event_id ) as $row ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv -- CSV download.
				fputcsv( $output, array_map( [ Attendee_Export::class, 'cell' ], $row ) );
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- CSV download.
			fclose( $output );
		}

		exit;
	}

	/**
	 * Resend a booking email.
	 */
	public static function resend(): void {
		if ( ! current_user_can( 'reevents_manage_bookings' ) ) {
			wp_die( esc_html__( 'Forbidden', 're-events' ), 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce checked below.
		$booking_id = isset( $_GET['booking_id'] ) ? absint( wp_unslash( $_GET['booking_id'] ) ) : 0;
		check_admin_referer( 'reevents_resend_email_' . $booking_id );

		if ( $booking_id ) {
			Booking_Emails::send( $booking_id, false );
		}

		$redirect = wp_get_referer();

		if ( ! $redirect ) {
			$redirect = admin_url( 'admin.php?page=reevents-attendees' );
		}

		wp_safe_redirect( add_query_arg( 'reevents_notice', 'resent', $redirect ) );
		exit;
	}
}

<?php
/**
 * Bookings admin page.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Admin;

use ReEvents\Services\Booking_Service;

/**
 * Lists bookings and handles cancellation.
 */
final class Bookings {

	/**
	 * Hook into WordPress.
	 */
	public static function register(): void {
		add_action( 'admin_menu', [ self::class, 'add_page' ], 13 );
		add_action( 'admin_post_reevents_cancel_booking', [ self::class, 'cancel' ] );
	}

	/**
	 * Add the bookings page.
	 */
	public static function add_page(): void {
		$hook = add_submenu_page(
			're-events',
			__( 'Bookings', 're-events' ),
			__( 'Bookings', 're-events' ),
			'reevents_manage_bookings',
			'reevents-bookings',
			[ self::class, 'render' ],
			5
		);

		if ( $hook ) {
			add_filter( 'manage_' . $hook . '_columns', [ Bookings_Table::class, 'default_columns' ], 0 );
		}
	}

	/**
	 * Render the bookings page.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'reevents_manage_bookings' ) ) {
			return;
		}

		$table = new Bookings_Table();
		$table->prepare_items();

		$bulk = get_transient( 'reevents_bookings_bulk_' . get_current_user_id() );

		if ( is_array( $bulk ) ) {
			delete_transient( 'reevents_bookings_bulk_' . get_current_user_id() );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Bookings', 're-events' ); ?></h1>
			<?php Export::button( 'bookings' ); ?>
			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice.
			$notice = isset( $_GET['reevents_notice'] ) ? sanitize_key( wp_unslash( $_GET['reevents_notice'] ) ) : '';

			if ( 'cancelled' === $notice ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Booking cancelled.', 're-events' ) . '</p></div>';
			}

			if ( is_array( $bulk ) ) {
				$message = sprintf(
					/* translators: 1: updated count, 2: failed count. */
					__( 'Bookings updated: %1$d. Failed: %2$d.', 're-events' ),
					(int) ( $bulk['updated'] ?? 0 ),
					(int) ( $bulk['failed'] ?? 0 )
				);
				echo '<div class="notice ' . ( ! empty( $bulk['failed'] ) ? 'notice-warning' : 'notice-success' ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
			}
			?>
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
				<input type="hidden" name="page" value="reevents-bookings" />
				<?php
				$table->search_box( __( 'Search bookings', 're-events' ), 'reevents-bookings' );
				$table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Cancel a booking.
	 */
	public static function cancel(): void {
		if ( ! current_user_can( 'reevents_manage_bookings' ) ) {
			wp_die( esc_html__( 'Forbidden', 're-events' ), 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce checked below.
		$booking_id = isset( $_GET['booking_id'] ) ? absint( wp_unslash( $_GET['booking_id'] ) ) : 0;
		check_admin_referer( 'reevents_cancel_booking_' . $booking_id );

		if ( $booking_id ) {
			Booking_Service::cancel( $booking_id );
		}

		$redirect = wp_get_referer();

		if ( ! $redirect ) {
			$redirect = admin_url( 'admin.php?page=reevents-bookings' );
		}

		wp_safe_redirect( add_query_arg( 'reevents_notice', 'cancelled', $redirect ) );
		exit;
	}
}

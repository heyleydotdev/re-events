<?php
/**
 * Bookings list table.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Admin;

use ReEvents\Data\Booking_Repository;
use ReEvents\Services\Booking_Service;
use ReEvents\Services\Currency;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Bookings list table.
 */
final class Bookings_Table extends \WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			[
				'singular' => 'booking',
				'plural'   => 'bookings',
				'ajax'     => false,
			]
		);
	}

	/**
	 * Columns.
	 *
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return self::default_columns();
	}

	/**
	 * Default columns.
	 *
	 * @return array<string, string>
	 */
	public static function default_columns(): array {
		return [
			'cb'        => '<input type="checkbox" />',
			'reference' => __( 'Reference', 're-events' ),
			'event'     => __( 'Event', 're-events' ),
			'status'    => __( 'Status', 're-events' ),
			'total'     => __( 'Total', 're-events' ),
			'created'   => __( 'Created', 're-events' ),
		];
	}

	/**
	 * Sortable columns.
	 *
	 * @return array<string, array<int, mixed>>
	 */
	public function get_sortable_columns(): array {
		return [
			'event'   => [ 'event', false ],
			'created' => [ 'created', true ],
		];
	}

	/**
	 * Prepare items.
	 */
	public function prepare_items(): void {
		$this->process_bulk_action();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only table state.
		$event_id = isset( $_GET['event'] ) ? absint( wp_unslash( $_GET['event'] ) ) : 0;
		$status   = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$orderby  = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'created';
		$order    = isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'desc';
		// phpcs:enable

		$per_page = $this->get_items_per_page( 'reevents_bookings_per_page', 20 );
		$paged    = $this->get_pagenum();
		$result   = ( new Booking_Repository() )->query(
			[
				'event_id' => $event_id,
				'status'   => $status,
				'search'   => $search,
				'orderby'  => $orderby,
				'order'    => $order,
				'limit'    => $per_page,
				'offset'   => ( $paged - 1 ) * $per_page,
			]
		);

		$this->items           = $result['items'];
		$this->_column_headers = [
			$this->get_columns(),
			get_hidden_columns( $this->screen ),
			$this->get_sortable_columns(),
			$this->get_primary_column_name(),
		];

		$this->set_pagination_args(
			[
				'total_items' => $result['total'],
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $result['total'] / $per_page ),
			]
		);
	}

	/**
	 * Bulk actions.
	 *
	 * @return array<string, string>
	 */
	public function get_bulk_actions(): array {
		return [
			'confirm' => __( 'Mark confirmed', 're-events' ),
			'cancel'  => __( 'Cancel', 're-events' ),
		];
	}

	/**
	 * Process bulk actions.
	 */
	private function process_bulk_action(): void {
		$action = $this->current_action();

		if ( ! in_array( $action, [ 'confirm', 'cancel' ], true ) || ! current_user_can( 'reevents_manage_bookings' ) ) {
			return;
		}

		check_admin_referer( 'bulk-bookings' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce checked above.
		$ids     = isset( $_REQUEST['booking'] ) ? array_map( 'absint', (array) wp_unslash( $_REQUEST['booking'] ) ) : [];
		$status  = 'confirm' === $action ? 'confirmed' : 'cancelled';
		$updated = 0;
		$failed  = 0;

		foreach ( $ids as $id ) {
			$result = Booking_Service::update_status( (int) $id, $status );

			if ( is_wp_error( $result ) || false === $result ) {
				++$failed;
			} else {
				++$updated;
			}
		}

		set_transient(
			'reevents_bookings_bulk_' . get_current_user_id(),
			[
				'updated' => $updated,
				'failed'  => $failed,
			],
			60
		);
	}

	/**
	 * Checkbox column.
	 *
	 * @param array<string, mixed> $item Row.
	 */
	public function column_cb( $item ): string {
		return '<input type="checkbox" name="booking[]" value="' . esc_attr( (string) ( $item['id'] ?? 0 ) ) . '" />';
	}

	/**
	 * Default column output.
	 *
	 * @param array<string, mixed> $item        Row.
	 * @param string               $column_name Column.
	 */
	public function column_default( $item, $column_name ): string {
		switch ( $column_name ) {
			case 'reference':
				return '<code>' . esc_html( (string) ( $item['reference'] ?? '' ) ) . '</code>';
			case 'event':
				return esc_html( (string) ( $item['event_title'] ?? '' ) );
			case 'status':
				return esc_html( (string) ( $item['status'] ?? '' ) );
			case 'total':
				$total = (int) ( $item['total_minor'] ?? 0 );

				return $total > 0 ? esc_html( Currency::format( $total, (string) ( $item['currency'] ?? '' ) ) ) : esc_html__( 'Free', 're-events' );
			case 'created':
				$date = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', (string) ( $item['created_utc'] ?? '' ), new \DateTimeZone( 'UTC' ) );

				return $date instanceof \DateTimeImmutable ? esc_html( wp_date( (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ), $date->getTimestamp() ) ) : '';
			default:
				return '';
		}
	}

	/**
	 * Row actions.
	 *
	 * @param array<string, mixed> $item        Row.
	 * @param string               $column_name Column.
	 * @param string               $primary     Primary column.
	 */
	protected function handle_row_actions( $item, $column_name, $primary ): string {
		if ( $column_name !== $primary ) {
			return '';
		}

		$booking_id = (int) ( $item['id'] ?? 0 );
		$actions    = [
			'quick_view' => '<a href="#" class="reevents-quick-view" data-booking-id="' . esc_attr( (string) $booking_id ) . '">' . esc_html__( 'Quick view', 're-events' ) . '</a>',
		];

		if ( 'cancelled' !== ( $item['status'] ?? '' ) ) {
			$url               = wp_nonce_url(
				admin_url( 'admin-post.php?action=reevents_cancel_booking&booking_id=' . $booking_id ),
				'reevents_cancel_booking_' . $booking_id
			);
			$actions['cancel'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Cancel', 're-events' ) . '</a>';
		}

		return $this->row_actions( $actions ) . parent::handle_row_actions( $item, $column_name, $primary );
	}

	/**
	 * Event and status filters.
	 *
	 * @param string $which Top or bottom.
	 */
	protected function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters.
		$event_id = isset( $_GET['event'] ) ? absint( wp_unslash( $_GET['event'] ) ) : 0;
		$status   = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		// phpcs:enable

		echo '<div class="alignleft actions reevents-event-filter">';
		echo '<label class="screen-reader-text" for="reevents-event-search-bookings">' . esc_html__( 'Search events', 're-events' ) . '</label>';
		echo '<input type="search" id="reevents-event-search-bookings" class="reevents-event-filter-search" placeholder="' . esc_attr__( 'Search events…', 're-events' ) . '" aria-controls="reevents-event-filter-bookings" />';
		echo '<label class="screen-reader-text" for="reevents-event-filter-bookings">' . esc_html__( 'Event', 're-events' ) . '</label>';
		echo '<select id="reevents-event-filter-bookings" name="event" class="reevents-event-filter-select" data-selected="' . esc_attr( (string) $event_id ) . '" data-all-label="' . esc_attr__( 'All events', 're-events' ) . '">';
		echo '<option value="0">' . esc_html__( 'All events', 're-events' ) . '</option>';

		if ( $event_id > 0 ) {
			$title = get_the_title( $event_id );
			echo '<option value="' . esc_attr( (string) $event_id ) . '" selected>' . esc_html( '' !== $title ? $title : sprintf( /* translators: %d: event ID. */ __( 'Event #%d', 're-events' ), $event_id ) ) . '</option>';
		}

		echo '</select>';
		echo '<select name="status"><option value="">' . esc_html__( 'All statuses', 're-events' ) . '</option>';

		foreach ( [ 'pending', 'confirmed', 'cancelled', 'rejected' ] as $option ) {
			echo '<option value="' . esc_attr( $option ) . '" ' . selected( $status, $option, false ) . '>' . esc_html( ucfirst( $option ) ) . '</option>';
		}

		echo '</select>';
		submit_button( __( 'Filter', 're-events' ), '', 'filter_action', false );
		echo '</div>';
	}

	/**
	 * Primary column.
	 */
	protected function get_primary_column_name(): string {
		return 'reference';
	}

	/**
	 * Empty state.
	 */
	public function no_items(): void {
		esc_html_e( 'No bookings found.', 're-events' );
	}
}
